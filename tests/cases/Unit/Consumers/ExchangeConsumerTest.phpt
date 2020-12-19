<?php declare(strict_types = 1);

namespace Tests\Cases;

use Bunny;
use FastyBird\ModulesMetadata\Exceptions as ModulesMetadataExceptions;
use FastyBird\ModulesMetadata\Loaders as ModulesMetadataLoaders;
use FastyBird\ModulesMetadata\Schemas as ModulesMetadataSchemas;
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
		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

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
		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

		$consumer->getQueueName();
	}

	public function testSetQueueName(): void
	{
		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

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

		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);

		$consumer->addHandler($handler);

		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'routing.key.one',
			[],
			$body
		);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

	/**
	 * @param mixed[] $data
	 *
	 * @dataProvider ./../../../fixtures/Consumers/consumeValidMessage.php
	 */
	public function testConsumeValidOriginMessage(
		array $data
	): void {
		try {
			$body = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			throw new Exceptions\InvalidStateException('Test data could not be prepared');
		}

		$schema = '{key: value}';

		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);
		$loader
			->shouldReceive('load')
			->andReturn($schema)
			->getMock();

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([$body, $schema])
			->andReturn(Utils\ArrayHash::from($data))
			->getMock();

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('process')
			->withArgs(function (string $origin, string $routingKey, Utils\ArrayHash $receivedData) use ($data): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::same('test.origin', $origin);
				Assert::equal($receivedData, Utils\ArrayHash::from($data));

				return true;
			})
			->andReturn(true)
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

		$schema = '{key: value}';

		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);
		$loader
			->shouldReceive('load')
			->andReturn($schema)
			->getMock();

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);
		$validator
			->shouldReceive('validate')
			->withArgs([$body, $schema])
			->andReturn(Utils\ArrayHash::from($data))
			->getMock();

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);
		$handler
			->shouldReceive('process')
			->withArgs(function (string $origin, string $routingKey, Utils\ArrayHash $receivedData) use ($data): bool {
				Assert::same('routing.key.one', $routingKey);
				Assert::same('test.origin', $origin);
				Assert::equal($receivedData, Utils\ArrayHash::from($data));

				return true;
			})
			->andThrow(new Exceptions\InvalidStateException('Could not handle message'))
			->times(1);

		$consumer->addHandler($handler);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

	public function testConsumeUnknownSchema(): void
	{
		$message = new Bunny\Message(
			'consumerTag',
			'deliveryTag',
			'redelivered',
			'exchange',
			'routing.key.one',
			[
				'origin' => 'test.origin',
			],
			''
		);

		$loader = Mockery::mock(ModulesMetadataLoaders\ISchemaLoader::class);
		$loader
			->shouldReceive('load')
			->andThrow(new ModulesMetadataExceptions\InvalidArgumentException('Message schema not found'))
			->getMock();

		$validator = Mockery::mock(ModulesMetadataSchemas\IValidator::class);

		$consumer = new Consumers\ExchangeConsumer($loader, $validator);

		$handler = Mockery::mock(Consumers\IMessageHandler::class);

		$consumer->addHandler($handler);

		Assert::equal(Consumers\IExchangeConsumer::MESSAGE_REJECT, $consumer->consume($message));
	}

}

$test_case = new ExchangeConsumerTest();
$test_case->run();
