<?php

namespace Tests\Unit;

use App\Core\Database\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class HasPublicUuidTest extends TestCase
{
    public function test_public_uuids_are_valid_and_unique(): void
    {
        $model = new class extends Model
        {
            use HasPublicUuid;
        };

        $first = $model->newPublicUuid();
        $second = $model->newPublicUuid();

        $this->assertTrue(Str::isUuid($first));
        $this->assertTrue(Str::isUuid($second));
        $this->assertNotSame($first, $second);
    }
}
