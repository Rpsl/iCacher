<?php

    class GdRoundedLib

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

        public function createRounded ( $radius = 10, $rate = 10, &$that )
        {

            $this->parentInstance       = $that;
            $this->currentDimensions    = $this->parentInstance->getCurrentDimensions();
            $this->workingImage         = $this->parentInstance->getWorkingImage();

            imagealphablending($this->workingImage, false);
            imagesavealpha($this->workingImage, true);

            $rs_radius  = $radius * $rate;
            $rs_size    = $rs_radius * 2;

            $corner = imagecreatetruecolor($rs_size, $rs_size);

            imagealphablending($corner, false);

            $trans = imagecolorallocatealpha($corner, 255, 255, 255, 127);

            imagefill($corner, 0, 0, $trans);

            $positions = array(
                array(0, 0, 0, 0),
                array($rs_radius, 0, $this->currentDimensions['width'] - $radius, 0),
                array($rs_radius, $rs_radius, $this->currentDimensions['width'] - $radius, $this->currentDimensions['height'] - $radius),
                array(0, $rs_radius, 0, $this->currentDimensions['height'] - $radius),
            );

            foreach ($positions as $pos)
            {
                imagecopyresampled(
                    $corner,
                    $this->workingImage,
                    $pos[0],
                    $pos[1],
                    $pos[2],
                    $pos[3],
                    $rs_radius,
                    $rs_radius,
                    $radius,
                    $radius
                );
            }

            $lx     = $ly = 0;
            $i      = -$rs_radius;
            $y2     = -$i;
            $r_2    = $rs_radius * $rs_radius;

            for (; $i <= $y2; $i++) {

                $y = $i;
                $x = sqrt($r_2 - $y * $y);

                $y += $rs_radius;
                $x += $rs_radius;

                imageline($corner, $x, $y, $rs_size, $y, $trans);
                imageline($corner, 0, $y, $rs_size - $x, $y, $trans);

                $lx = $x;
                $ly = $y;
            }

            foreach ($positions as $i => $pos)
            {
                imagecopyresampled(
                    $this->workingImage,
                    $corner,
                    $pos[2],
                    $pos[3],
                    $pos[0],
                    $pos[1],
                    $radius,
                    $radius,
                    $rs_radius,
                    $rs_radius
                );
            }


            return $that;
        }
    }

    $pt = PhpThumb::getInstance();
    $pt->registerPlugin( 'GdRoundedLib', 'gd' );
