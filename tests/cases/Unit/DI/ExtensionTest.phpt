<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\RabbitMqPlugin;
use FastyBird\RabbitMqPlugin\Connections;
use FastyBird\RabbitMqPlugin\Consumer;
use FastyBird\RabbitMqPlugin\Subscribers;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

/**
 * @testCase
 */
final class ExtensionTest extends BaseTestCase
{

	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		Assert::notNull($container->getByType(RabbitMqPlugin\Exchange::class));

		Assert::notNull($container->getByType(Connections\IRabbitMqConnection::class));

		Assert::notNull($container->getByType(Consumer\IConsumer::class));

		Assert::notNull($container->getByType(Subscribers\InitializeSubscriber::class));
	}

}

$test_case = new ExtensionTest();
$test_case->run();
