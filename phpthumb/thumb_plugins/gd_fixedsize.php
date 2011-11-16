<?php

/**
 * GD FixedSize Lib Plugin
 *
 * This plugin allows you to create fixed resize with white background
 *
 * @package PhpThumb
 * @subpackage Plugins
 */
class GdFixedSizeLib
{
    /**
     * Instance of GdThumb passed to this class
     *
     * @var GdThumb
     */
    protected $parentInstance;
    protected $currentDimensions;
    protected $workingImage;
    protected $newImage;
    protected $options;

    public function resizeFixedSize($width, $height, $color = '#FFFFFF', &$that)
    {
        $this->parentInstance       = $that;

        $this->parentInstance->resize($width, $height);

        $this->currentDimensions    = $this->parentInstance->getCurrentDimensions();
        $this->workingImage         = $this->parentInstance->getWorkingImage();
        $this->newImage             = $this->parentInstance->getOldImage();
        $this->options              = $this->parentInstance->getOptions();

        $this->workingImage = imagecreatetruecolor($width, $height);

        imagealphablending($this->workingImage, true);

        $rgb = $this->hex2rgb($color, false);
        $colorToPaint = imagecolorallocatealpha($this->workingImage, $rgb[0], $rgb[1], $rgb[2], 0);
        imagefilledrectangle($this->workingImage, 0, 0, $width, $height, $colorToPaint);

        imagecopyresampled
        (
            $this->workingImage,
            $this->newImage,
            intval(($width - $this->currentDimensions['width']) / 2),
            intval(($height - $this->currentDimensions['height']) / 2),
            0,
            0,
            $this->currentDimensions['width'],
            $this->currentDimensions['height'],
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        );

        $this->parentInstance->setOldImage($this->workingImage);
        $this->currentDimensions['width']     = $width;
        $this->currentDimensions['height']    = $height;
        $this->parentInstance->setCurrentDimensions($this->currentDimensions);

        return $that;
    }

    /**
     * Converts a hex color to rgb tuples
     *
     * @return mixed
     * @param string $hex
     * @param bool $asString
     */
    protected function hex2rgb ($hex, $asString = false)
    {
        // strip off any leading #
        if (0 === strpos($hex, '#'))
        {
           $hex = substr($hex, 1);
        }
        elseif (0 === strpos($hex, '&H'))
        {
           $hex = substr($hex, 2);
        }

        // break into hex 3-tuple
        $cutpoint = ceil(strlen($hex) / 2)-1;
        $rgb = explode(':', wordwrap($hex, $cutpoint, ':', $cutpoint), 3);

        // convert each tuple to decimal
        $rgb[0] = (isset($rgb[0]) ? hexdec($rgb[0]) : 0);
        $rgb[1] = (isset($rgb[1]) ? hexdec($rgb[1]) : 0);
        $rgb[2] = (isset($rgb[2]) ? hexdec($rgb[2]) : 0);

        return ($asString ? "{$rgb[0]} {$rgb[1]} {$rgb[2]}" : $rgb);
    }
}

$pt = PhpThumb::getInstance();
$pt->registerPlugin('GdFixedSizeLib', 'gd');