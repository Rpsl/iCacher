<?php

    /**
     * @author  Rpsl ( 2010 )   < im.vitman@gmail.com >
     * @link    http://github.com/Rpsl/iCacher
     * @link    http://blog.rpsl.info
     * @version 0.5
     */

    /**
     * Скрипт iCacher создан что бы облечить кеширование изображений на сайте
     * и организовать лаконичную возможность генерации картинок различных размеров.
     * iCacher является т.н. роутером для http://phpthumb.gxdlabs.com/ и не будет
     * работать правильно при отсутвие данной библиотеки.
     *
     * Для правильно работы подразумевается соблюдение нескольких правил:
     *
     *  1. В папке MAIN_FOLDER хранятся оригинальные изображения.
     *  2. В папку CACHE_FOLDER будут храниться измененные изображения.
     *  3. При обращение к несуществующему файлу из папки CACHE_FOLDER происходит
     *      перенаправление на данный файл*, который в свою очередь создает
     *      необходимый файл либо возвращет 404 ошибку.
     *          * .htaccess rewrite rule:
     *              RewriteRule   ^images_folder/([0-9a-z]+)/([0-9a-z]+)/(.*)$  iCacher.php?param=$1&size=$2&file=$3 [L,QSA]
     *  4. После генерации изображений они должны быть доступны по прямому запросу.
     *  5. Для обновления миниатюр вы должны самостоятельно организовать удаление
     *      созданых скриптом файлов.
     *      В крайнем случае можно использовать GET параметр flush с любым значением.
     *
     *
     *  При необходимости создавайте собственные плагины или ф-ции обработки.
     */


    /**
     *  Папка с оригинальными изображениями
     */
    define( 'MAIN_FOLDER',      $_SERVER['DOCUMENT_ROOT'] . '/images_folder');

    /**
     * CACHE_FOLDER в большинстве случаев является простым алиасом для MAIN_FOLDER,
     * но может быть полезна, если кеш картинок у вас лежит в не стандартной папке,
     * например вынесен на отдельный домен и отдается другим web сервером.
     *
     * Будьте бдительны, если после генерации миниатюр они не будут доступны по
     * URL который используется первый раз, то они не будут кешироваться на
     * стороне клиента.
     */
    define( 'CACHE_FOLDER',     MAIN_FOLDER );

    /**
     * Укажите путь к папке phpthumb
     */
    define( 'PHPTHUMB_FOLDER',  $_SERVER['DOCUMENT_ROOT'] . '/phpthumb');

    /**
     * Качество создаваемых изображений.
     */
    define( 'JPEG_QUALITY', 80);

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
        AND file_exists(realpath( MAIN_FOLDER . '/' . $_GET['file'] ))
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
            require_once PHPTHUMB_FOLDER . '/ThumbLib.inc.php';

            $options = array(
                'resizeUp'              => true,
                'jpegQuality'           => JPEG_QUALITY,
                'correctPermissions'    => true
            );

            $T = PhpThumbFactory::create( MAIN_FOLDER . '/' . $_GET['file'] , $options);

            switch( $_GET['param'] ):
                case 'rn':
                    /**
                     * Обычный ресайз
                     */
                    $file = ResizeNormal( $T, $_GET['file'], $size );
                break;

                case 'rl':
                    /**
                     * Ресайз по большей стороне
                     */
                    $file = ResizeLargeSide( $T, $_GET['file'], $size );
                break;

                case 'ra':
                    /**
                     * Адаптивный ресайз
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
                     * Скругленные углы
                     */
                    $file = RoundedCorners( $T, $_GET['file'], $size, 10, 10 );
                break;

                case 'fx':
                    /**
                     * Фиксированный ресайз
                     */
                    $file = FixedResize( $T, $_GET['file'], $size );
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

    /**
     * Обычный ресайз
     *
     * Стандартное изменение размера картинки, по двум сторонам
     * или только по ширине, если параметр $size[1] не будет задан.
     *
     * @param   Object    $T (phpThumb)
     * @param   string    $file
     * @param   mixed     $size
     * @param   string    $folder
     *
     * @return  string    $file_path
     */
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

    /**
     * Ресайз по большей стороне
     *
     * Тоже самое что и обычный ресайз, но параметр $size[1]
     * не учитывается.
     * Будут определены размеры исходного файла и в зависимости от того,
     * какая сторона больше - она будет ужата до $size[0]
     *
     * @param   Object    $T (phpThumb)
     * @param   string    $file
     * @param   mixed     $size
     *
     * @return  string    $file_path
     */
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

    /**
    * Адаптивный ресайз
    *
    * Всегда возвращает картинку заданных размеров.
    * Работает хитро, но очень круто.
    *
    * @param    Object    $T (phpThumb)
    * @param    string    $file
    * @param    mixed     $size
    * @param    string    $folder
    *
    * @return  string    $file_path
    */
    function ResizeAdaptive( $t, $file, $size, $folder = 'ra' )
    {
        $t->adaptiveResize( $size[0], $size[1] );

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;
    }

    /**
     * Обрезание картинки от центра
     *
     * @param   Object    $T (phpThumb)
     * @param   string    $file
     * @param   mixed     $size
     * @param   string    $folder
     *
     * @return  string    $file_path
     */
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

    /**
     * Скругленные углы
     *
     * @param   Object    $T (phpThumb)
     * @param   string    $file
     * @param   mixed     $size
     * @param   int       $radius
     * @param   int       $rate
     *
     * @return  string    $file_path
     */
    function RoundedCorners( $t, $file, $size, $folder = 'rc', $radius = 10, $rate = 10 )
    {
        $t->adaptiveResize( $size[0], $size[1] )->createRounded( $radius, $rate );

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;
    }

    /**
     * Фиксированный ресайз
     *
     * Изменение размера картинки в фиксированный размер с задником, по двум сторонам
     * или только по ширине, если параметр $size[1] не будет задан.
     *
     * @param   Object    $T (phpThumb)
     * @param   string    $file
     * @param   mixed     $size
     * @param   string    $color
     * @param   string    $folder
     *
     * @return  string    $file_path
     */
    function FixedResize( $t, $file, $size, $color = '#FFFFFF', $folder = 'fx' )
    {
        if( is_array( $size ) )
        {
            $t->resizeFixedSize( $size[0], $size[1], $color );
        }
        else
        {
            $t->resizeFixedSize( $size, $size, $color );
        }

        $save_size = SaveSize( $size );

        $file = CACHE_FOLDER . '/' . $folder . '/'. $save_size . '/' . $file;

        return $file;
    }

    /**
     * Сохранение сгенерированной картинки
     *
     * @param Object    $T (phpthumb)
     * @param string    $filepath
     */
    function SaveFile( $T, $file )
    {
        if( is_object( $T ) AND !empty( $file ) )
        {
            extract( pathinfo( $file ), EXTR_PREFIX_SAME, "");

            if( !is_dir( $dirname ) )
            {
                mkdir( $dirname, 0777, true);
            }

            $T->save( $file );
            $T->show();
        }
    }

    /**
     * Генерация пути размера
     *
     * @param mixed $size
     * @return sting $save_size
     */
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
     *
     * @param   mixed $array
     * @return  sting $key
     */
    function max_key ( $array )
    {
        if( is_array( $array ) ) { foreach ( $array as $key => $val ) { if ( $val == max( $array ) ) { return $key; } } }
    }

    /**
     * Возвращает ключ элемента с минимальным значением
     *
     * @param   mixed $array
     * @return  sting $key
     */
    function min_key ( $array )
    {
        if( is_array( $array ) ) { foreach ( $array as $key => $val ) { if ( $val == min( $array ) ) { return $key; } } }
    }
