<?php declare(strict_types = 1);

namespace Tests\Cases;

use Bunny;
use FastyBird\RabbitMqPlugin\Consumers;
use FastyBird\RabbitMqPlugin\Exceptions;
use Mockery;
use Nette\Utils;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * @testCase
 */
final class ExchangeConsumerTest extends BaseMockeryTestCase
{

	public function testEmptyHandlers(): void
	{
		$consumer = new Consumers\ExchangeConsumer();

		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'',
			[],
			''
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

	/**
	 * @throws FastyBird\RabbitMqPlugin\Exceptions\InvalidStateException
	 */
	public function testNotSetQueueName(): void
	{
		$consumer = new Consumers\ExchangeConsumer();

		$consumer->getQueueName();
	}

	public function testSetQueueName(): void
	{
		$consumer = new Consumers\ExchangeConsumer();

		$consumer->setQueueName('queueNameSet');

		Assert::equal('queueNameSet', $consumer->getQueueName());
	}

	/**
	 * @param mixed[] $data
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeNoOriginMessage(
		array $data
	): void {
		try {
			$body = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$consumer = new Consumers\ExchangeConsumer();

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('process')
			->withArgs(function (string $routingKey, string $origin, string $content) use ($body): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::same('test.origin', $origin);
				Assert::equal($body, $content);

				return true;
			})
			->andReturn(Consumers\IExchangeConsumer::MESSAGE_ACK)
			->times(1);

		$consumer->addHandler($handler);

		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'routing.key.one',
			[
				'origin' => 'test.origin',
			],
			$body
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_ACK, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeSingleOriginMessage(
		array $data
	): void {
		try {
			$body = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$consumer = new Consumers\ExchangeConsumer();

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('process')
			->withArgs(function (string $routingKey, string $origin, string $content) use ($body): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::same('test.origin', $origin);
				Assert::equal($body, $content);

				return true;
			})
			->andReturn(Consumers\IExchangeConsumer::MESSAGE_ACK)
			->times(1);

		$consumer->addHandler($handler);

		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'routing.key.one',
			[
				'origin' => 'test.origin',
			],
			$body
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_ACK, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeMultiOriginMessage(
		array $data
	): void {
		try {
			$body = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$consumer = new Consumers\ExchangeConsumer();

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('process')
			->withArgs(function (string $routingKey, string $origin, string $content) use ($body): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::same('test.origin', $origin);
				Assert::equal($body, $content);

				return true;
			})
			->andReturn(Consumers\IExchangeConsumer::MESSAGE_ACK)
			->times(1);

		$consumer->addHandler($handler);

		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'routing.key.one',
			[
				'origin' => 'test.origin',
			],
			$body
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_ACK, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeInvalidMessage(
		array $data
	): void {
		try {
			$body = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'routing.key.one',
			[
				'origin' => 'test.origin',
			],
			$body
		);

		$consumer = new Consumers\ExchangeConsumer();

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('process')
			->withArgs(function (string $routingKey, string $origin, string $content) use ($body): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::same('test.origin', $origin);
				Assert::equal($content, $body);

				return true;
			})
			->andThrow(new Exceptions\InvalidStateException('Could not handle message'))
			->times(1);

		$consumer->addHandler($handler);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

}

$test_case = new ExchangeConsumerTest();
$test_case->run();
