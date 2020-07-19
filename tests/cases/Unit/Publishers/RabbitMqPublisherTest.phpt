<?php declare(strict_types = 1);

namespace Tests\Cases;

use Bunny;
use DateTimeImmutable;
use FastyBird\DateTimeFactory;
use FastyBird\NodeExchange;
use FastyBird\NodeExchange\Connections;
use FastyBird\NodeExchange\Publishers;
use Mockery;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use Psr\Log;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * @testCase
 */
final class RabbitMqPublisherTest extends BaseMockeryTestCase
{

	public function testPublishMessage(): void
	{
		$rabbitMqChannel = Mockery::mock(Bunny\Channel::class);
		$rabbitMqChannel
			->shouldReceive('publish')
			->withArgs(function ($message, $headers, $exchangeName): bool {
				Assert::true(array_key_exists('origin', $headers));
				Assert::same('origin.test', $headers['origin']);
				Assert::same(NodeExchange\Constants::RABBIT_MQ_MESSAGE_BUS_EXCHANGE_NAME, $exchangeName);

				return true;
			})
			->andReturn(true)
			->times(1);

		$rabbitMq = Mockery::mock(Connections\IRabbitMqConnection::class);
		$rabbitMq
			->shouldReceive('getChannel')
			->andReturn($rabbitMqChannel)
			->times(1);

		$dateFactory = Mockery::mock(DateTimeFactory\DateTimeFactory::class);
		$dateFactory
			->shouldReceive('getNow')
			->andReturn(new DateTimeImmutable())
			->times(1);

		$logger = Mockery::mock(Log\LoggerInterface::class);
		$logger
			->shouldReceive('info')
			->withArgs(function ($message): bool {
				Assert::same('[FB:EXCHANGE] Received message was pushed into data exchange', $message);

				return true;
			})
			->times(1);

		$publisher = new Publishers\RabbitMqPublisher('origin.test', $rabbitMq, $dateFactory, $logger);

		$publisher->publish('routing.key.path', [
			'key_one' => 'value_one',
			'key_two' => 'value_two',
		]);
	}

}

$test_case = new RabbitMqPublisherTest();
$test_case->run();
