<?php

namespace Tests\Unit;

use App\Exceptions\OperationException;
use App\Repositories\ExampleRepository;
use App\Services\ExampleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExampleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_write_is_rolled_back_and_reported_as_an_operation_error(): void
    {
        $repository = $this->createMock(ExampleRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->willThrowException(new RuntimeException('yazılamadı'));

        $service = new ExampleService($repository);

        try {
            $service->create(['title' => 'Saha', 'note' => null]);
            $this->fail('İşlem hatası bekleniyordu.');
        } catch (OperationException $exception) {
            $this->assertSame('İşlem tamamlanamadı.', $exception->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }

        $this->assertDatabaseCount('examples', 0);
    }
}
