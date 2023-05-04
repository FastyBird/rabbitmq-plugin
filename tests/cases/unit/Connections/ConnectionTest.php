<?php declare(strict_types = 1);

namespace FastyBird\Plugin\RabbitMq\Tests\Cases\Unit\Connections;

use FastyBird\Library\Bootstrap\Exceptions as BootstrapExceptions;
use FastyBird\Plugin\RabbitMq\Connections;
use FastyBird\Plugin\RabbitMq\Tests;
use Nette;

final class ConnectionTest extends Tests\Cases\Unit\BaseTestCase
{

	public function testDefaultValues(): void
	{
		$config = new Connections\Connection('127.0.0.1', 1_234);

		self::assertSame('127.0.0.1', $config->getHost());
		self::assertSame(1_234, $config->getPort());
		self::assertSame('guest', $config->getUsername());
		self::assertSame('guest', $config->getPassword());
	}

	/**
	 * @throws BootstrapExceptions\InvalidArgument
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testConfiguredValues(): void
	{
		$container = $this->createContainer(__DIR__ . '/../../../fixtures/Connections/customConnection.neon');

		$connection = $container->getByType(Connections\Connection::class);

		self::assertSame('rabbitmq.loc', $connection->getHost());
		self::assertSame(1_234, $connection->getPort());
	}

}
