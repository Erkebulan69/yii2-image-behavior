<?php

namespace erkebulan69\image_behavior;

use Imagine\Image\Box;
use Imagine\Image\Color;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Yii;
use Yii\db\ActiveRecord;
use Yii\base\Behavior;
use Yii\helpers\FileHelper;
use Yii\imagine\Image;

/**
 * @author Yerkebulan Kazykenov <erkebulan69@gmail.com>
 */
class ImageBehavior extends Behavior
{
    const DS = DIRECTORY_SEPARATOR;

    public $rootPath;
    public $webPath;
    public $images = [];
    public $watermark = '@backend/web/img/watermark.png';

    protected $defaultImageSettings = [
        'variable' => null,
        'extension' => 'jpg',
        'size' => [500, 500],
        'watermark' => false,
        'quality' => ['jpeg_quality' => 80, 'png_compression_level' => 9],
        'afterInsert' => null,
        'beforeDelete' => null,
    ];

    private $_imagine = null;
    private $_folder = null;

    private function getImagine()
    {
        if ($this->_imagine === null) {
            $this->_imagine = Image::getImagine();
        }

        return $this->_imagine;
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterInsert',
        ];
    }

    public function init()
    {
        parent::init();
        $this->rootPath = Yii::getAlias($this->rootPath);

        foreach ($this->images as $imageVariable => $imageSettings) {
            $this->images[$imageVariable] = array_merge($this->defaultImageSettings, $imageSettings);
        }
    }

    public function afterInsert()
    {
        try {
            $model = $this->owner;
            $dir = $this->getDirectory();

            foreach ($this->images as $name => $settings) {
                if ($settings['variable'] === null) {
                    throw new \Exception('Variable in ImageBehavior is not set');
                } else {
                    $variable = $settings['variable'];
                }

                if (isset($model->$variable) && $model->$variable) {
                    $this->removePreviousImages($name);

                    $uploadedImages = [];
                    $isMultiple = null;
                    if (is_array($model->$variable)) {
                        $uploadedImages = $model->$variable;
                        $isMultiple = 1;
                    } else {
                        $uploadedImages[] = $model->$variable;
                        $isMultiple = null;
                    }

                    foreach ($uploadedImages as $uploadedImage) {
                        $path = $dir . self::DS . $this->imageName($name, $isMultiple);
                        if ($isMultiple !== null) {
                            $isMultiple++;
                        }

                        $imagine = $this->getImagine();
                        $image = $imagine->open($uploadedImage->tempName);

                        $image = $image->thumbnail(new Box(
                            $settings['size'][0],
                            $settings['size'][1]
                        ));

                        if ($settings['watermark'] == true) {
                            $this->pasteWatermark($image);
                        }

                        if (strtolower($settings['extension']) != 'png') {
                            $canvas = $imagine->create($image->getSize(), new Color('#fff'));
                            $canvas->paste($image, new Point(0, 0));
                            $canvas->save(
                                $path,
                                $settings['quality']
                            );
                        } else {
                            $image->save($path);
                        }

                        $afterInsert = $settings['afterInsert'];
                        if (is_callable($afterInsert)) {
                            $afterInsert($path, $image->getSize());
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function pasteWatermark(ImageInterface $image)
    {
        $imagine = $this->getImagine();

        $size = $image->getSize();
        $watermark = $imagine->open(Yii::getAlias($this->watermark));
        $wSize = $watermark->getSize();

        if ($size->getWidth() <= $wSize->getWidth()) {
            $ratio = $size->getWidth() / $wSize->getWidth();
            $watermark->resize(new Box($wSize->getWidth() * $ratio, $wSize->getHeight() * $ratio));
            $wSize = $watermark->getSize();
        }

        if ($size->getHeight() <= $wSize->getHeight()) {
            $ratio = $size->getHeight() / $wSize->getHeight();
            $watermark->resize(new Box($wSize->getWidth() * $ratio, $wSize->getHeight() * $ratio));
            $wSize = $watermark->getSize();
        }

        $bottomRight = new Point($size->getWidth() - $wSize->getWidth(), $size->getHeight() - $wSize->getHeight());
        $image->paste($watermark, $bottomRight);
    }

    public function removePreviousImages($name = '')
    {
        $previousImages = FileHelper::findFiles($this->getDirectoryPath(), [
            'only' => [$name . '*.' . $this->images[$name]['extension']],
        ]);
        foreach ($previousImages as $previousImage) {
            if ($name) {
                $beforeDelete = $this->images[$name]['beforeDelete'];
                if (is_callable($beforeDelete)) {
                    $beforeDelete($previousImage);
                }
            }
            unlink($previousImage);
        }
    }

    public function removeThumbnails($name = '')
    {
        $thumbnails = FileHelper::findFiles($this->getDirectoryPath(), [
            'only' => [$name . '*.*.' . $this->images[$name]['extension']],
        ]);
        foreach ($thumbnails as $thumbnail) {
            unlink($thumbnail);
        }
    }

    public function beforeDelete()
    {
        foreach ($this->images as $name => $settings) {
            $this->removePreviousImages($name);
        }
        $this->deleteDirectory();
    }

    private function getFolder()
    {
        if ($this->_folder === null) {
            /** @var ActiveRecord $model */
            $model = $this->owner;
            $unique_name = $model->primaryKey;

            $hash = md5($unique_name);
            $this->_folder = substr($hash, 0, 2) . self::DS . substr($hash, 2, 2) . self::DS . substr($hash, 4, 2) . self::DS . $unique_name;
        }

        return $this->_folder;
    }

    public function getDirectoryPath($isWeb = false)
    {
        $dir = ($isWeb ? $this->webPath : $this->rootPath) . self::DS . $this->getFolder();
        return $dir;
    }

    protected function getDirectory()
    {
        $dir = $this->getDirectoryPath();
        if (!file_exists($dir) && !is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        return $dir;
    }

    protected function deleteDirectory()
    {
        $dir = $this->getDirectoryPath();
        if (file_exists($dir) || is_dir($dir)) {
            FileHelper::removeDirectory($dir);
        }

        return;
    }

    public function images($name, $width = null, $height = null, $stretch = false)
    {
        $result = [];

        if (!file_exists($this->getDirectoryPath()))
            return $result;

        $imageFiles = FileHelper::findFiles($this->getDirectoryPath(),
            ['only' => [$name . '_' . '*' . '.' . $this->images[$name]['extension']]]
        );

        foreach ($imageFiles as $imageFile) {

            $imageFilename = pathinfo($imageFile, PATHINFO_FILENAME);
            if (!preg_match("/{$name}_\d$/", $imageFilename)) {
                continue;
            }

            $resultPath = $this->image($imageFilename, $width, $height, $stretch);
            if ($resultPath) {
                $result[] = $resultPath;
            }
        }

        return $result;
    }

    public function image($name, $width = null, $height = null, $stretch = false)
    {
        $matches = [];
        preg_match('/(\w+)_([0-9]+)/', $name, $matches);
        if ($matches) {
            $filterName = $matches[1];
            $filterNumber = $matches[2];
        } else {
            $filterName = $name;
            $filterNumber = null;
        }

        $imageName = $this->imageName($filterName, $filterNumber);
        $imageNameResized = $this->imageName($filterName, $filterNumber, $width, $height, $stretch);

        $isResize = ($imageName != $imageNameResized);
        $imagePath = $this->getDirectoryPath() . self::DS . $imageName;
        $imagePathResized = $this->getDirectoryPath() . self::DS . $imageNameResized;

        if ($isResize == true) {
            if (!file_exists($imagePathResized)) {
                if (!file_exists($imagePath)) {
                    return null;
                }

                $this->resize($imagePath, $imagePathResized, $width, $height, $stretch);
            }

            return $this->imageLink($filterName, $filterNumber, $width, $height, $stretch);

        } else {
            if (file_exists($imagePath)) {
                return $this->imageLink($filterName, $filterNumber);
            }
        }

        return null;
    }

    private function imageName($name, $number = null, $width = null, $height = null, $stretch = false)
    {
        return $name .
            ($number !== null ? "_{$number}" : '') .
            (($height !== null && $width !== null) ? ".{$width}x{$height}" : '') .
            ($stretch !== false ? 's' : '') .
            '.' . $this->images[$name]['extension'];
    }

    public function imageLink($name, $number, $width = null, $height = null, $stretch = false)
    {
        return str_replace('\\', '/', $this->getDirectoryPath(true) . self::DS . $this->imageName($name, $number, $width, $height, $stretch));
    }

    protected function resize($inImagePath, $outImagePath, $width, $height, $stretch)
    {
        $imagine = $this->getImagine();
        $inImage = $imagine->open($inImagePath);
        $outImage = null;

        /** @var \Imagine\Image\ImageInterface $thumbnail */
        $thumbnail = $inImage->thumbnail(new Box($width, $height));
        if ($stretch === false) {
            $outImage = $thumbnail;
        } else {
            $thumbnailBox = $thumbnail->getSize();
            $tHeight = $thumbnailBox->getHeight();
            $tWidth = $thumbnailBox->getWidth();

            $imagine = $this->getImagine();
            $point = new Point(($width - $tWidth) / 2, ($height - $tHeight) / 2);
            $background = new Color('#fff');
            $outImage = $imagine->create(new Box($width, $height), $background);
            $outImage->paste($thumbnail, $point);
        }

        $outImage->save($outImagePath, ['jpeg_quality' => 100, 'png_compression_level' => 9]);
    }
}