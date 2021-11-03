<?php

namespace App\Tests\Functional\Command;

use App\Command\IpGetCommand;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class IpGetCommandTest extends KernelTestCase
{
    private const COLLECTION_TAG = 'service-id';

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
     * @dataProvider runEmptyRequiredValueDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunEmptyRequiredValue(array $input, int $expectedReturnCode): void
    {
        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public function runEmptyRequiredValueDataProvider(): array
    {
        return [
            'empty collection tag' => [
                'input' => [],
                'expectedReturnCode' => IpGetCommand::EXIT_CODE_EMPTY_COLLECTION_TAG,
            ],
        ];
    }

    /**
     * @dataProvider runSuccessDataProvider
     *
     * @param array<mixed> $floatingIpResponseData
     */
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
            '--' . IpGetCommand::OPTION_COLLECTION_TAG => self::COLLECTION_TAG,
        ]);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessDataProvider(): array
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
