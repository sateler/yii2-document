Yii2 plugin for a document model
===========================

Yii2-document stores documents in your local database or any filesystem supported by [Flysystem](https://flysystem.thephpleague.com/).

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist sateler/yii2-document "^1.1"
```

or add

```
"sateler/yii2-document": "^1.1"
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

Configuration
-----
```php
'documentManager' => [
    'class' => \sateler\document\DocumentManager::class,
    // Define default filesystem, of none given sql storage is used
    'filesystemId' => 'awsS3',
    'filesystems' => [
        // A flysystem filesystem config
        'awsS3' => [
            'class' => AwsS3Filesystem::class,
            'bucket' => 'bucket-name',
            'region' => 'us-east-1',
            'prefix' => 'path',
            'key' => 'key',
            'secret' => 'secret',
        ],
    ],
],
```

Usage
-----

Once installed, you can now use `sateler\document\Document` in your relations, and redirect to 
`['documents/view', 'id' => $docId]` to view or download.

Create a document and save it:
```php
$doc = new Document();
$doc->name = 'filename';
$doc->mime_type = 'mime/type';
$doc->contents = file_get_contents('/path/to/file');
$doc->save();
```

Get a document and send it:
```php
$doc = Document::findOne($id)
$res = Yii::$app->response;
$res->format = Response::FORMAT_RAW;
$res->setDownloadHeaders($doc->name, $doc->mime_type, true);
$res->data = $doc->contents;
$res->send();
```
