<?php declare(strict_types = 1);

/**
 * RabbitMqPluginExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           19.06.20
 */

namespace FastyBird\RabbitMqPlugin\DI;

use FastyBird\RabbitMqPlugin;
use FastyBird\RabbitMqPlugin\Commands;
use FastyBird\RabbitMqPlugin\Connections;
use FastyBird\RabbitMqPlugin\Consumers;
use FastyBird\RabbitMqPlugin\Publishers;
use Nette;
use Nette\DI;
use Nette\Schema;
use stdClass;

/**
 * Message exchange extension container
 *
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class RabbitMqPluginExtension extends DI\CompilerExtension
{

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbRabbitMqPlugin'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new RabbitMqPluginExtension());
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'origin'   => Schema\Expect::string()
				->required(),
			'rabbitMQ' => Schema\Expect::structure([
				'connection' => Schema\Expect::structure([
					'host'     => Schema\Expect::string()
						->default('127.0.0.1'),
					'port'     => Schema\Expect::int(5672),
					'vhost'    => Schema\Expect::string('/'),
					'username' => Schema\Expect::string('guest'),
					'password' => Schema\Expect::string('guest'),
				]),
				'queue'      => Schema\Expect::structure([
					'name' => Schema\Expect::string()
						->required(),
				]),
				'routing'    => Schema\Expect::structure([
					'keys' => Schema\Expect::array([])
						->items(Schema\Expect::string()),
				]),
			]),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		$builder->addDefinition(null)
			->setType(Connections\RabbitMqConnection::class)
			->setArguments([
				'host'     => $configuration->rabbitMQ->connection->host,
				'port'     => $configuration->rabbitMQ->connection->port,
				'vhost'    => $configuration->rabbitMQ->connection->vhost,
				'username' => $configuration->rabbitMQ->connection->username,
				'password' => $configuration->rabbitMQ->connection->password,
			]);

		$builder->addDefinition(null)
			->setType(Consumers\ExchangeConsumer::class)
			->addSetup('?->setQueueName(?)', [
				'@self',
				$configuration->rabbitMQ->queue->name,
			]);

		$builder->addDefinition(null)
			->setType(Publishers\RabbitMqPublisher::class)
			->setArgument('origin', $configuration->origin);

		$builder->addDefinition(null)
			->setType(RabbitMqPlugin\Exchange::class)
			->setArgument('routingKeys', $configuration->rabbitMQ->routing->keys);

		$builder->addDefinition(null)
			->setType(Commands\ConsumerCommand::class);
	}

	/**
	 * {@inheritDoc}
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/** @var string $messagesConsumerServiceName */
		$messagesConsumerServiceName = $builder->getByType(Consumers\ExchangeConsumer::class, true);

		/** @var DI\Definitions\ServiceDefinition $messagesConsumerService */
		$messagesConsumerService = $builder->getDefinition($messagesConsumerServiceName);

		$consumerHandlersServices = $builder->findByType(Consumers\IMessageHandler::class);

		foreach ($consumerHandlersServices as $consumerHandlersService) {
			$messagesConsumerService->addSetup('?->addHandler(?)', [
				'@self',
				$consumerHandlersService,
			]);
		}
	}

}
