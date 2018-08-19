Yii2 Image Behavior
===
Yii2 Image behavior for uploading and attaching, resizing and stretching images for ActiveRecord  
This extension do not use DB to store image path and information.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist erkebulan69/yii2-image-behavior "*"
```

or add

```
"erkebulan69/yii2-image-behavior": "*"
```

to the require section of your `composer.json` file.


Usage
-----

### Attach behavior

Attach `ImageBehavior` in your Model:

```php
class Product extends \yii\db\ActiveRecord
{
    /* 
     * Constants values are just image names in file system.
     * These constants will be used to access stored images.
     */
    const IMAGE_MAIN = 'main';
    const IMAGES_SECONDARY = 'secondary';
    
    /*
     * variables, that will be used for image/images uploading
     */
    public $image_main;
    public $images_secondary;

    public function behaviors()
    {
        return [
            'image' => [
                'class' => ImageBehavior::className(),
                'rootPath' => '@frontend/web/img/uploads/products', // absolute path to directory, where images will be stored 
                'webPath' => '/img/uploads/products', // web path by which images can be accessed
                'images' => [
                    self::IMAGE_MAIN => [
                        'variable' => 'image_main', // public variable in model, from where to store image/images. Only required parameter
                        'extension' => 'jpg', // extension of image. By default "jpg"
                        'size' => [800,800], // max size of the image
                    ],
                    self::IMAGES_SECONDARY => [
                        'variable' => 'images_secondary',
                    ],
                ],
            ],
        ];
    }
    ...
```

### Handle images from form

Receive uploaded image/images from request into our public variables.  
```php
public function actionCreate($provider_id)
{
    $model = new Product();

    if ($model->load(Yii::$app->request->post())) {
        $model->image_main = UploadedFile::getInstance($model, 'image_main');
        $model->images_secondary = UploadedFile::getInstances($model, 'images_secondary');
        if ($model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
    }
    
    return $this->render('create', [
        'model' => $model,
    ]);
}
```

### Access stored images

When the `ActiveRecord` save method succeed, the uploaded image/images will be stored in behavior's specified rootPath.

Now to access image, call `image()` for single uploaded image and `images()` for multiple.  
Example:

*Single image*
 
`<?php echo Html::img($model->image(Product::IMAGE_MAIN)) ?>`

*Multiple images*
```php
<?php foreach ($model->images(Product::IMAGES_SECONDARY) as $image_secondary): ?>
    <?php echo Html::img($image_secondary) ?>
<?php endforeach; ?>
```

*Get resized image without stretching*

`<?php echo Html::img($model->image(Product::IMAGE_MAIN), 60, 60) ?>`

*Get resized image with stretching*

`<?php echo Html::img($model->image(Product::IMAGE_MAIN), 60, 60, true) ?>`


You can get `image()`, `images()` code completion hints by adding `ImageBehavior` in class as `@mixin`.  
Example:

```php
/*
 * @mixin ImageBehavior
 */
class Product extends \yii\db\ActiveRecord
{
...
```
