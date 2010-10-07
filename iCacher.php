<?
    define( 'MAIN_FOLDER',      $_SERVER['DOCUMENT_ROOT'] . '/images_folder');
    define( 'CACHE_FOLDER',     MAIN_FOLDER ); //  ??? mb need
    define( 'PHPTHUM_FOLDER',   $_SERVER['DOCUMENT_ROOT'] . '/phpthumb');
    define( 'JPEG_QUALITY',     80);


    error_reporting( ~E_ALL );
    ini_set( 'display_errors', 0 );

    /* Paranoic mode */
    $_GET['file'] = preg_replace( '/[^A-Za-z0-9_.-]/',  '', $_GET['file'] );
    $_GET['size'] = preg_replace( '/[^x0-9]/',          '', $_GET['size'] );
    
    $image = CACHE_FOLDER . '/' . $_GET['param'] . '/' . $_GET['size'] . '/' . $_GET['file'];

    if( file_exists( $image ) AND !isset( $_GET['flush'] ) )
    {
        $file_info = getimagesize( $image );
        header( 'Content-type: ' . $file_info ['mime'] );

        echo file_get_contents( $image );
        die();
    }
    
    unset( $image );

    if( !empty( $_GET['file'] )
        AND !empty( $_GET['size'] )
        AND !empty( $_GET['param'] )
        AND file_exists( MAIN_FOLDER . '/' . $_GET['file'] )
    )
    {
        if( !is_numeric( $_GET['size'] ) )
        {
            $size = explode( 'x', $_GET['size'] );
        }
        else
        {
            $size[0] = $_GET['size'];
            $size[1] = 0;
        }

        try
        {
            include '/ThumbLib.inc.php';

            $options = array(
                'resizeUp'              => true,
                'jpegQuality'           => JPEG_QUALITY,
                'correctPermissions'    => true
                //'preserveAlpha'         => true,
                //'preserveTransparency'  => true,
                //'alphaMaskColor'        => array(255, 255, 255, 255)
            );

            $T = PhpThumbFactory::create( MAIN_FOLDER . '/' . $_GET['file'] , $options);

            $file = '';

            switch( $_GET['param'] ):
                case 'rn':
                    /**
                     * Обычный ресайз
                     *
                     * Стандартное изменение размера картинки, по двум сторонам
                     * или только по ширине, если параметр $size[1] не будет задан.
                     */

                    $file = ResizeNormal( $T, $_GET['file'], $size );
                break;

                case 'rl':
                    /**
                     * Ресайз по большей стороне
                     *
                     * Тоже самое что и обычный ресайз, но параметр $size[1]
                     * не учитывается.
                     * Будут определены размеры исходного файла и в зависимости от того,
                     * какая сторона больше - она будет ужата до $size[0]
                     */
                    $file = ResizeLargeSide( $T, $_GET['file'], $size );
                break;

                case 'ra':
                    /**
                     * Адаптивный ресайз
                     *
                     * Всегда возвращает картинку заданных размеров.
                     * Работает хитро, но очень круто.
                     */

                    $file = ResizeAdaptive( $T, $_GET['file'], $size );
                break;

                case 'cc':
                    /**
                     * Обрезание картинки от центра
                     */

                    $file = CropFromCenter( $T, $_GET['file'], $size );
                break;

                case 'rc':
                    /**
                     * Rounded Corners
                     */

                    $file = RoundedCorners( $T, $_GET['file'], $size );
                break;

                default:
                    header("HTTP/1.0 404 Not Found");
                    die();
                break;
            endswitch;

            SaveFile( $T, $file );

        }
        catch (Exception $e)
        {
            header("HTTP/1.0 404 Not Found");
            die();
        }
    }
    else
    {
        header("HTTP/1.0 404 Not Found");
        die();
    }


    function ResizeNormal( $t , $file, $size, $folder = 'rn' )
    {
        if( is_array( $size ) )
        {
            $t->resize( $size[0], $size[1] );
        }
        else
        {
            $t->resize( $size );
        }

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;
    }

    function ResizeLargeSide( $t , $file, $size )
    {
        list( $size_ori[0], $size_ori[1] ) = getimagesize( MAIN_FOLDER . '/' . $file);


        $key = max_key( $size_ori );

        if( $key == 0 )
        {
            // Если ширина больше высоты
            $size = $size[0];
        }
        else
        {
            // Если высота больше ширины
            $size = array( 0, $size[0] );
        }

        $file = ResizeNormal( $t, $file, $size, 'rl' );

        return $file;
    }

    function ResizeAdaptive( $t, $file, $size, $folder = 'ra' )
    {
        $t->adaptiveResize( $size[0], $size[1] );

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;
    }

    function CropFromCenter( $t, $file, $size, $folder = 'cc' )
    {
        if( empty( $size[0] ) OR empty( $size[1] ) )
        {
            $size[ min_key( $size ) ] = $size[ max_key( $size ) ];
        }

        $t->CropFromCenter( $size[0], $size[1] );

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;

    }

    function RoundedCorners( $t, $file, $size, $folder = 'rc' )
    {
        $t->adaptiveResize( $size[0], $size[1] )->createRounded(10,10);

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;
    }

    function SaveFile( $T, $file )
    {
        if( is_object( $T ) AND !empty( $file ) )
        {
            extract( pathinfo( $file ), EXTR_PREFIX_SAME, "");

            if( !is_dir( $dirname ) )
            {
                mkdir( $dirname, 0777, true);
            }

            $T->save( $file, 'png' );
            $T->show();
        }
    }

    function SaveSize( $size )
    {
        if( is_array( $size ) )
        {
            if( !empty( $size[0] ) AND !empty( $size[1] ) )
            {
                $save_size = $size[0] . 'x' . $size[1] ;
            }
            elseif( !empty( $size[0] ) AND empty( $size[1] ) )
            {
                $save_size = $size[0];
            }
            elseif( empty( $size[0] ) AND !empty( $size[1] ) )
            {
                $save_size = $size[1];
            }
        }
        else
        {
            $save_size = $size;
        }

        return $save_size;
    }


    // ----- Вспомогательные функции -----------

    /**
     * Возвращает ключ элемента с максмальным значением
     * @param mixed $array
     */
    function max_key ( $array )
    {
        if( is_array( $array ) ) { foreach ( $array as $key => $val ) { if ( $val == max( $array ) ) { return $key; } } }
    }

    /**
     * Возвращает ключ элемента с минимальным значением
     * @param mixed $array
     */
    function min_key ( $array )
    {
        if( is_array( $array ) ) { foreach ( $array as $key => $val ) { if ( $val == min( $array ) ) { return $key; } } }
    }