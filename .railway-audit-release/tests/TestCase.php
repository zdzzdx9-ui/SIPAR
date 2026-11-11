<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Testing\File;

abstract class TestCase extends BaseTestCase
{
    protected function fakeImage(string $name = 'photo.jpg', int $width = 640, int $height = 480): File
    {
        return File::image($name, $width, $height);
    }
}
