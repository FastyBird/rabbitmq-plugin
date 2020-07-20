<?php declare(strict_types = 1);

namespace Tests\Cases;

use Bunny;
use FastyBird\NodeExchange\Consumers;
use FastyBird\NodeExchange\Exceptions;
use FastyBird\NodeMetadata\Schemas as NodeMetadataSchemas;
use Mockery;
use Nette\Utils;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use Psr\Log;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * @testCase
 */
final class ExchangeConsumerTest extends BaseMockeryTestCase
{

	public function testEmptyHandlers(): void
	{
		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

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
	 * @throws FastyBird\NodeExchange\Exceptions\InvalidStateException
	 */
	public function testNotSetQueueName(): void
	{
		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$consumer->getQueueName();
	}

	public function testSetQueueName(): void
	{
		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$consumer->setQueueName('queueNameSet');

		Assert::equal('queueNameSet', $consumer->getQueueName());
	}

	/**
	 * @param mixed[] $data
	 * @param string $schema
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeNoOriginMessage(
		array $data,
		string $schema
	): void {
		try {
			$content = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([
				$content,
				$schema,
			])
			->andReturn(Utils\ArrayHash::from($data))
			->times(1);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('getSchema')
			->andReturn($schema)
			->times(1)
			->getMock()
			->shouldReceive('process')
			->withArgs(function (string $routingKey, Utils\ArrayHash $payload) use ($data): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::equal(Utils\ArrayHash::from($data), $payload);

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
			$content
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_ACK, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 * @param string $schema
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeSingleOriginMessage(
		array $data,
		string $schema
	): void {
		try {
			$content = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([
				$content,
				$schema,
			])
			->andReturn(Utils\ArrayHash::from($data))
			->times(1);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('getSchema')
			->andReturn($schema)
			->times(1)
			->getMock()
			->shouldReceive('process')
			->withArgs(function (string $routingKey, Utils\ArrayHash $payload) use ($data): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::equal(Utils\ArrayHash::from($data), $payload);

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
			$content
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_ACK, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 * @param string $schema
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeMultiOriginMessage(
		array $data,
		string $schema
	): void {
		try {
			$content = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([
				$content,
				$schema,
			])
			->andReturn(Utils\ArrayHash::from($data))
			->times(1);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('getSchema')
			->andReturn($schema)
			->times(1)
			->getMock()
			->shouldReceive('process')
			->withArgs(function (string $routingKey, Utils\ArrayHash $payload) use ($data): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::equal(Utils\ArrayHash::from($data), $payload);

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
			$content
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_ACK, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 * @param string $schema
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeInvalidOriginMessage(
		array $data,
		string $schema
	): void {
		try {
			$content = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('getSchema')
			->andReturn(null)
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
			$content
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 * @param string $schema
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeInvalidMessage(
		array $data,
		string $schema
	): void {
		try {
			$content = Utils\Json::encode($data);

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
			$content
		);

		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([
				$content,
				$schema,
			])
			->andReturn(Utils\ArrayHash::from($data))
			->times(1);

		$logger = Mockery::mock(Log\LoggerInterface::class);
		$logger
			->shouldReceive('debug')
			->withArgs([
				'[FB:EXCHANGE] Received message could not be handled',
				[
					'message' => [
						'routingKey' => $message->routingKey,
						'headers'    => $message->headers,
					],
				],
			])
			->times(1);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('getSchema')
			->andReturn($schema)
			->times(1)
			->getMock()
			->shouldReceive('process')
			->withArgs(function (string $routingKey, Utils\ArrayHash $payload) use ($data): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::equal(Utils\ArrayHash::from($data), $payload);

				return true;
			})
			->andThrow(new Exceptions\InvalidStateException('Could not handle message'))
			->times(1);

		$consumer->addHandler($handler);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 * @param string $schema
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeNotValidSchemaMessage(
		array $data,
		string $schema
	): void {
		try {
			$content = Utils\Json::encode($data);

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
			$content
		);

		$validator = Mockery::mock(NodeMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([
				$content,
				$schema,
			])
			->andThrow(new Exceptions\InvalidStateException('Could not validate message'))
			->times(1);

		$logger = Mockery::mock(Log\LoggerInterface::class);
		$logger
			->shouldReceive('debug')
			->withArgs([
				'[FB:EXCHANGE] Received message is not valid',
				[
					'message' => [
						'routingKey' => $message->routingKey,
						'headers'    => $message->headers,
					],
				],
			])
			->times(1);

		$consumer = new Consumers\ExchangeConsumer(
			$validator,
			$logger
		);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('getSchema')
			->andReturn($schema)
			->times(1)
			->getMock();

		$consumer->addHandler($handler);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

}

$test_case = new ExchangeConsumerTest();
$test_case->run();
