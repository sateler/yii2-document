A generic DB document store
===========================
A generic DB document store

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist sateler/yii2-document "*"
```

or add

```
"sateler/yii2-document": "*"
```

to the require section of your `composer.json` file.

Once the extension is installed, add namespace to console config:

```php
return [
    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationNamespaces' => [
                'sateler\document\migrations',
            ],
        ],
    ],
];
```

And controller to web config:

```php
return [
    'controllerMap' => [
        'documents' => [
            'class' => 'sateler\document\controllers\DocumentController',
        ]
    ],
];
```

Usage
-----

Once installed, you can now use `sateler\document\Document` in your relations, and redirect to 
`['documents/view', 'id' => $docId]` to view or download.

