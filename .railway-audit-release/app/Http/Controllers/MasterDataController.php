<?php

namespace App\Http\Controllers;

use App\Exports\StudentsExport;
use App\Models\Alat;
use App\Models\ItemUnit;
use App\Models\KategoriAlat;
use App\Models\User;
use App\Services\PhotoCompressor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class MasterDataController extends Controller
{
    public function equipment(Request $request): View
    {
        $items = Alat::with('kategori')->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(fn ($builder) => $builder->where('nama_alat', 'like', $term)->orWhere('kode_alat', 'like', $term));
        })->orderBy('nama_alat')->paginate(15)->withQueryString();

        return view('admin.equipment.index', ['items' => $items, 'categories' => KategoriAlat::orderBy('nama_kategori')->get()]);
    }

    public function storeEquipment(Request $request, PhotoCompressor $photoCompressor): RedirectResponse
    {
        $minimumStock = $request->input('status_alat') === 'Tersedia' ? 1 : 0;
        $data = $request->validate([
            'kode_alat' => ['required', 'string', 'max:50', 'unique:alat,kode_alat'],
            'nama_alat' => ['required', 'string', 'max:150'],
            'id_kategori' => ['required', 'exists:kategori_alat,id_kategori'],
            'stok_total' => ['required', 'integer', 'min:'.$minimumStock, 'max:10000'],
            'kondisi' => ['required', 'string', 'max:80'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'status_alat' => ['required', Rule::in(['Tersedia', 'Maintenance', 'Rusak'])],
        ]);
        $newPhoto = $request->hasFile('foto')
            ? $photoCompressor->store($request->file('foto'), 'alat', 'foto')
            : null;
        unset($data['foto']);
        if ($newPhoto) {
            $data['foto'] = $newPhoto;
        }
        $data['stok_tersedia'] = $data['stok_total'];
        $data['stok_maintenance'] = 0;
        $data['stok_rusak'] = 0;
        $data['qr_code'] = (string) Str::uuid();
        if ($data['status_alat'] !== 'Tersedia') {
            $data['stok_tersedia'] = 0;
            $data[$data['status_alat'] === 'Maintenance' ? 'stok_maintenance' : 'stok_rusak'] = $data['stok_total'];
        }
        $keepPhoto = false;
        try {
            Alat::create($data);
            $keepPhoto = true;
        } finally {
            if (! $keepPhoto && $newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
        }

        return back()->with('success', 'Alat baru berhasil ditambahkan.');
    }

    public function updateEquipment(Request $request, Alat $alat, PhotoCompressor $photoCompressor): RedirectResponse
    {
        $minimumStock = $request->input('status_alat') === 'Tersedia' ? 1 : 0;
        $data = $request->validate([
            'kode_alat' => ['required', 'string', 'max:50', Rule::unique('alat', 'kode_alat')->ignore($alat->id_alat, 'id_alat')],
            'nama_alat' => ['required', 'string', 'max:150'],
            'id_kategori' => ['required', 'exists:kategori_alat,id_kategori'],
            'stok_total' => ['required', 'integer', 'min:'.$minimumStock, 'max:10000'],
            'kondisi' => ['required', 'string', 'max:80'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'status_alat' => ['required', Rule::in(['Tersedia', 'Maintenance', 'Rusak'])],
            'perbaiki_dari' => ['nullable', Rule::in(['Baik', 'Maintenance', 'Rusak'])],
            'jumlah_diperbaiki' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        unset($data['foto']);
        $repairSource = $data['perbaiki_dari'] ?? null;
        $repairQuantity = (int) ($data['jumlah_diperbaiki'] ?? 0);
        if (($repairSource === null) !== ($repairQuantity === 0)) {
            throw ValidationException::withMessages([
                'jumlah_diperbaiki' => 'Pilih kondisi asal dan jumlah unit yang benar-benar selesai diperbaiki.',
            ]);
        }
        unset($data['perbaiki_dari'], $data['jumlah_diperbaiki']);

        $oldPhoto = null;
        $newPhoto = null;
        $keepPhoto = false;
        try {
            DB::transaction(function () use ($request, $alat, &$data, &$oldPhoto, &$newPhoto, $photoCompressor, $repairSource, $repairQuantity): void {
                $lockedAlat = Alat::whereKey($alat->id_alat)->lockForUpdate()->firstOrFail();
                $borrowed = max(0, $lockedAlat->stok_total - $lockedAlat->stok_tersedia - $lockedAlat->stok_maintenance - $lockedAlat->stok_rusak);
                $statusChanged = $data['status_alat'] !== $lockedAlat->status_alat;
                $targetCondition = match ($data['status_alat']) {
                    'Tersedia' => 'Baik',
                    'Maintenance' => 'Maintenance',
                    'Rusak' => 'Rusak',
                };
                if ($repairQuantity > 0) {
                    $validTransition = ($targetCondition === 'Baik' && in_array($repairSource, ['Maintenance', 'Rusak'], true))
                        || (in_array($targetCondition, ['Maintenance', 'Rusak'], true) && $repairSource === 'Baik');
                    if (! $validTransition || $repairSource === $targetCondition) {
                        throw ValidationException::withMessages([
                            'perbaiki_dari' => 'Pilih kondisi asal yang berbeda dan sesuai dengan kondisi tujuan.',
                        ]);
                    }
                }

                if ($lockedAlat->itemUnits()->exists()) {
                    $units = ItemUnit::where('alat_id', $lockedAlat->id_alat)
                        ->orderBy('asset_id')
                        ->lockForUpdate()
                        ->get();
                    if ($units->count() !== (int) $data['stok_total']) {
                        throw ValidationException::withMessages([
                            'stok_total' => 'Jumlah stok harus sama dengan jumlah Asset ID terdaftar. Tambah atau keluarkan unit fisik melalui pengelolaan inventaris.',
                        ]);
                    }

                    if ($repairQuantity > 0) {
                        $unitsToUpdate = $units->where('status', 'Tersedia')->where('condition', $repairSource);
                        if ($unitsToUpdate->count() < $repairQuantity) {
                            throw ValidationException::withMessages([
                                'jumlah_diperbaiki' => 'Jumlah unit tersedia dengan kondisi asal tersebut tidak mencukupi.',
                            ]);
                        }
                        ItemUnit::whereIn('item_unit_id', $unitsToUpdate->take($repairQuantity)->pluck('item_unit_id'))
                            ->update(['condition' => $targetCondition]);
                    } elseif ($statusChanged) {
                        throw ValidationException::withMessages([
                            'jumlah_diperbaiki' => 'Perubahan kondisi Asset ID harus menyebutkan kondisi asal dan jumlah unit.',
                        ]);
                    }

                    $lockedAlat->fill($data);
                    $lockedAlat->syncStockFromItemUnits();
                    $lockedAlat->save();
                } else {
                    $maintenance = $lockedAlat->stok_maintenance;
                    $damaged = $lockedAlat->stok_rusak;
                    $available = $lockedAlat->stok_tersedia;
                    if ($repairQuantity > 0) {
                        if ((int) $data['stok_total'] !== $lockedAlat->stok_total) {
                            throw ValidationException::withMessages([
                                'stok_total' => 'Ubah kondisi unit dan jumlah stok total pada proses terpisah.',
                            ]);
                        }
                        if ($repairSource === 'Maintenance' && $repairQuantity > $maintenance) {
                            throw ValidationException::withMessages([
                                'jumlah_diperbaiki' => 'Jumlah yang diubah melebihi stok maintenance.',
                            ]);
                        }
                        if ($repairSource === 'Rusak' && $repairQuantity > $damaged) {
                            throw ValidationException::withMessages([
                                'jumlah_diperbaiki' => 'Jumlah yang diubah melebihi stok rusak.',
                            ]);
                        }
                        if ($repairSource === 'Baik' && $repairQuantity > $available) {
                            throw ValidationException::withMessages([
                                'jumlah_diperbaiki' => 'Jumlah yang diubah melebihi stok tersedia.',
                            ]);
                        }
                        if ($targetCondition === 'Baik') {
                            $available += $repairQuantity;
                            if ($repairSource === 'Maintenance') {
                                $maintenance -= $repairQuantity;
                            } else {
                                $damaged -= $repairQuantity;
                            }
                        } else {
                            $available -= $repairQuantity;
                            if ($targetCondition === 'Maintenance') {
                                $maintenance += $repairQuantity;
                            } else {
                                $damaged += $repairQuantity;
                            }
                        }
                    } elseif ($statusChanged) {
                        throw ValidationException::withMessages([
                            'jumlah_diperbaiki' => 'Perubahan kondisi stok harus menyebutkan kondisi asal dan jumlah unit.',
                        ]);
                    }

                    if ((int) $data['stok_total'] < $borrowed + $maintenance + $damaged) {
                        throw ValidationException::withMessages([
                            'stok_total' => 'Stok total tidak boleh lebih kecil dari jumlah dipinjam dan tidak tersedia.',
                        ]);
                    }
                    if ($repairQuantity === 0) {
                        $available = (int) $data['stok_total'] - $borrowed - $maintenance - $damaged;
                    }
                    if ($targetCondition === 'Baik' && $available < 1) {
                        throw ValidationException::withMessages([
                            'jumlah_diperbaiki' => 'Sediakan minimal satu unit layak pakai sebelum status alat menjadi tersedia.',
                        ]);
                    }
                    $data['stok_tersedia'] = $available;
                    $data['stok_maintenance'] = $maintenance;
                    $data['stok_rusak'] = $damaged;
                    $lockedAlat->update($data);
                }

                $oldPhoto = $lockedAlat->foto;
                if ($request->hasFile('foto')) {
                    $newPhoto = $photoCompressor->store($request->file('foto'), 'alat', 'foto');
                    $lockedAlat->update(['foto' => $newPhoto]);
                }
            });
            $keepPhoto = true;
        } finally {
            if (! $keepPhoto && $newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return back()->with('success', 'Data alat berhasil diperbarui.');
    }

    public function deleteEquipment(Alat $alat): RedirectResponse
    {
        if ($alat->detailPeminjaman()->exists()) {
            return back()->withErrors(['alat' => 'Alat yang pernah tercatat dalam transaksi tidak dapat dihapus. Ubah statusnya menjadi Rusak atau Maintenance.']);
        }
        $alat->delete();

        return back()->with('success', 'Alat berhasil dihapus.');
    }

    public function categories(): View
    {
        return view('admin.categories', ['categories' => KategoriAlat::withCount('alat')->orderBy('nama_kategori')->get()]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['nama_kategori' => ['required', 'string', 'max:100', 'unique:kategori_alat,nama_kategori']]);
        KategoriAlat::create($data);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function updateCategory(Request $request, KategoriAlat $kategori): RedirectResponse
    {
        $data = $request->validate(['nama_kategori' => ['required', 'string', 'max:100', Rule::unique('kategori_alat', 'nama_kategori')->ignore($kategori->id_kategori, 'id_kategori')]]);
        $kategori->update($data);

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function deleteCategory(KategoriAlat $kategori): RedirectResponse
    {
        if ($kategori->alat()->exists()) {
            return back()->withErrors(['kategori' => 'Kategori masih digunakan oleh data alat.']);
        }
        $kategori->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    public function students(): View
    {
        return view('admin.students', ['students' => User::where('role', 'siswa')->orderBy('nama')->paginate(15)]);
    }

    public function exportStudentsExcel()
    {
        return Excel::download(new StudentsExport, 'sipar-data-siswa-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportStudentsPdf(): Response
    {
        return Pdf::loadView('admin.students-pdf', [
            'students' => User::where('role', 'siswa')->orderBy('nama')->get(),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait')->download('sipar-data-siswa-'.now()->format('Ymd-His').'.pdf');
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'nis_nip' => ['required', 'string', 'max:30', 'unique:users,nis_nip'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        $data['role'] = 'siswa';
        User::create($data);

        return back()->with('success', 'Akun siswa berhasil dibuat.');
    }

    public function updateStudent(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role !== 'siswa', 404);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'nis_nip' => ['required', 'string', 'max:30', Rule::unique('users', 'nis_nip')->ignore($user->id_user, 'id_user')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id_user, 'id_user')],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);

        return back()->with('success', 'Akun siswa berhasil diperbarui.');
    }

    public function deleteStudent(User $user): RedirectResponse
    {
        return DB::transaction(function () use ($user): RedirectResponse {
            $student = User::whereKey($user->id_user)
                ->where('role', 'siswa')
                ->lockForUpdate()
                ->firstOrFail();
            if ($student->peminjaman()->exists()) {
                throw ValidationException::withMessages([
                    'siswa' => 'Akun dengan riwayat peminjaman tidak dapat dihapus.',
                ]);
            }
            $student->delete();

            return back()->with('success', 'Akun siswa berhasil dihapus.');
        });
    }
}
