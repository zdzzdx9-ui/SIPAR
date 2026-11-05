<?php

return [
    'admin_password' => env('SIPAR_ADMIN_PASSWORD'),
    'student_password' => env('SIPAR_TEST_STUDENT_PASSWORD'),
    'technician_password' => env('TECHNICIAN_PASSWORD'),
    'technician_username' => env('TECHNICIAN_USERNAME', 'teknisi'),
    'technician_email' => env('TECHNICIAN_EMAIL', 'teknisi@sipar.sch.id'),
];
