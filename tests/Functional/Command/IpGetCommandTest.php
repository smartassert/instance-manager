<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\IpGetCommand;
use App\Command\Option;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class IpGetCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;

    private const COLLECTION_TAG = 'service_id';

    private IpGetCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(IpGetCommand::class);
        \assert($command instanceof IpGetCommand);
        $this->command = $command;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @param array<mixed> $floatingIpResponseData
     */
    #[DataProvider('runSuccessDataProvider')]
    public function testRunSuccess(
        array $floatingIpResponseData,
        string $expectedOutput,
    ): void {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => (string) json_encode([
                    'floating_ips' => $floatingIpResponseData,
                ]),
            ])
        );

        $output = new BufferedOutput();
        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => self::COLLECTION_TAG,
        ]);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public static function runSuccessDataProvider(): array
    {
        return [
            'none' => [
                'floatingIpResponseData' => [],
                'expectedOutput' => '',
            ],
            'one, not assigned to anything' => [
                'floatingIpResponseData' => [
                    [
                        'ip' => '127.0.0.100',
                        'droplet' => null,
                    ],
                ],
                'expectedOutput' => '',
            ],
            'one, assigned to an instance' => [
                'floatingIpResponseData' => [
                    [
                        'ip' => '127.0.0.200',
                        'droplet' => [
                            'id' => 123,
                            'tags' => [
                                self::COLLECTION_TAG,
                            ],
                        ],
                    ],
                ],
                'expectedOutput' => '127.0.0.200',
            ],
        ];
    }
}
