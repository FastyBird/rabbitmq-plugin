<?php declare(strict_types = 1);

return [
	'one' => [
		[
			'attributeOne'   => 'String value',
			'attributeTwo'   => 20,
			'attributeThree' => false,
			'attributeFour'  => null,
		],
		file_get_contents(__DIR__ . '/validator.schema.json'),
	],
];
