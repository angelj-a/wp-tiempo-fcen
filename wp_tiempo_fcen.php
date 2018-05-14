<?php
/**
 * @package UBAFCENTiempo
 */
/*
    Plugin Name: UBA FCEN - Widget del Tiempo (no oficial)
    Plugin URI:
    Description: El tiempo en estación meteorológica del Grupo de DCAO
    Version: 0.6.2
    Author: angelj-a
    Author URI: https://github.com/angelj-a
    License: GPLv2 or later
*/

if ( !defined( 'ABSPATH' ) ) exit;

define('TIEMPO_EM_DCAO_VERSION', '0.6.2');
define('TIEMPO_EM_DCAO_WIDGET_URL', plugin_dir_url( __FILE__ ) );

class WidgetTiempoFCEN extends WP_Widget {
    /**
     * Registrar el widget con WordPress.
     * (los datos del constructor son visibles en Apariencia->Widgets)
     */
    function __construct() {
        parent::__construct(
            'weather', // Base ID
            __( 'Widget del Tiempo - UBA FCEN (no oficial)', 'text_domain' ), // Name
            array( 'description' => __( 'El tiempo en estación meteorológica del Grupo de DCAO', 'text_domain' ), ) // Args
        );
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget( $args, $instance ) {
        //echo $args['before_widget'];
        //if ( ! empty( $instance['title'] ) ) {
        //  echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        //}
        //echo __( esc_attr( 'Hello, World!' ), 'text_domain' );
        //echo $args['after_widget'];

        $api_tiempo = new API_SitioWeb_DCAO();
        $temperatura = $api_tiempo->obtenerTemperatura();
        $cielo = $api_tiempo->obtenerCielo();
        $pronostico = $api_tiempo->obtenerPronostico();

        $DIR_IMAGENES = TIEMPO_EM_DCAO_WIDGET_URL;
        $URL_ICONO_TEMPERATURA = esc_url($DIR_IMAGENES.'icons/temperatura/'.(round($temperatura)<0 ? '_' : '') . abs(round($temperatura)) .'.png');
        $URL_ICONO_CIELO = esc_url($DIR_IMAGENES.'icons/'.$cielo.'.png');

        $widget = '
            <a class="weather" href="http://tiempo.at.fcen.uba.ar/ubapronr.htm" target="blank">
                <img src="'.$URL_ICONO_TEMPERATURA.'" class="number" title="'.esc_attr($temperatura).' ºC" />
                <img src="'.$URL_ICONO_CIELO.'" class="icon" title="'.esc_attr($pronostico).'"/>
                <span>+</span>
            </a>';
        echo $widget;
    }

    /**
     * Back-end widget form.
     *
     * @see WP_Widget::form()
     *
     * @param array $instance Previously saved values from database.
     */
    public function form( $instance ) {

    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     *
     * @return array Updated safe values to be saved.
     */
    public function update( $new_instance, $old_instance ) {
        //$instance = array();
        //$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        //return $instance;
    }
}


interface iAPI_Tiempo {

    public function obtenerTemperatura();
    public function obtenerCielo();

}

class API_Dummy implements iAPI_Tiempo
{
    private $vars = array();

    public function obtenerTemperatura(){
        return 19;
    }

    public function obtenerCielo(){
        return '0cloud';
    }
}

class API_SitioWeb_DCAO implements iAPI_Tiempo
{
    protected $temperatura;
    protected $cielo;
    protected $pronostico;
    protected $mapeo_iconos_dcao_a_iconos_widget = array(
        "Soleado.jpg"=> "0cloud",       // "n_0cloud"
        "Niebla.jpg" => "0cloud_fog",       // "n_0cloud_fog"
        "Mayordes.jpg" => "1cloud_norain",  // "n_1cloud_norain"
        "Parcianu.jpg" => "2cloud_norain",  // "n_2cloud_norain"
        "Variable.jpg" => "2cloud_norain",  // "n_2cloud_norain"
        "mayornu.jpg" => "3cloud_norain",   // "n_3cloud_norain"
        "Cubierto.jpg" => "4cloud_norain",  // No tenía - usar mismo icono que día
        "Lloviznas.jpg" => "4cloud_lightrain",  // No tenía - usar mismo icono que día
        "Lluvias.jpg" => "4cloud_modrain",  // No tenía - usar mismo icono que día
        "Chaparrones.jpg" => "4cloud_heavyrain",// No tenía - usar mismo icono que día
        "Tormenta.gif" => "4cloud_thunders",    // No tenía - usar mismo icono que día
        "Nieve.jpg" => "4cloud_lightsnow",  // No tenía - usar mismo icono que día
        "Granizo.jpg" => "4cloud_hail",     // No tenía - creado a mano - usar mismo icono que día
    );

    public function __construct () {
    }

    public function obtenerTemperatura(){
        if ( false === ( $ubafcen_tiempo_temperatura = get_transient( 'ubafcen_tiempo_temperatura' ) ) ) {
            // parseo la página de DCAO para extraer la temperatura
            $respuesta_http = wp_remote_get("http://estacion.at.fcen.uba.ar/tp_pop.htm", array('timeout' => 4));
            if (is_wp_error($respuesta_http) || !is_array($respuesta_http) || (is_array($respuesta_http) && $respuesta_http['response']['code'] >= 400 )){
                $ubafcen_tiempo_temperatura = 0;
            }
            else {
                $pagina_html = $respuesta_http['body'];
                $DOM = new DOMDocument;
                $DOM->loadHTML($pagina_html);

                foreach($DOM->getElementsByTagName('font') as $item) {
                    if (preg_match("/(?P<temperatura>(\d+(\.\d)?)) \x{00B0}C/u", $item->textContent, $coincidencias)){
                        $ubafcen_tiempo_temperatura = (float)$coincidencias['temperatura'];
                        break;
                    }
                }
            }

            set_transient( 'ubafcen_tiempo_temperatura', $ubafcen_tiempo_temperatura, 10 * MINUTE_IN_SECONDS );
        }
        $this->temperatura = $ubafcen_tiempo_temperatura;
        return $this->temperatura;
    }

    public function obtenerCielo(){
        if ( false === ( $ubafcen_tiempo_cielo = get_transient( 'ubafcen_tiempo_cielo' ) ) ) {
            $respuesta_http = wp_remote_get("http://tiempo.at.fcen.uba.ar/ubapronr.htm", array('timeout' => 4));
            if (is_wp_error($respuesta_http) || !is_array($respuesta_http) || (is_array($respuesta_http) && $respuesta_http['response']['code'] >= 300 )){
                $ubafcen_tiempo_cielo = '0cloud';
            }
            else {
                $pagina_html = $respuesta_http['body'];
                $DOM = new DOMDocument;
                $DOM->loadHTML($pagina_html);

                $imagenes = $DOM->getElementsByTagName('img');
                // posición tentativa de la imagen con el pronóstico del día
                $img_pronostico = $imagenes->item(1)->getAttribute('src');
                preg_match("/.*\/(?P<icono_dcao>.*\.(jpg|gif))/", $img_pronostico, $coincidencias);
                $icono_tiempo = $coincidencias['icono_dcao'];
                if ($icono_tiempo){
                    $ubafcen_tiempo_cielo = $this->mapeo_iconos_dcao_a_iconos_widget[$icono_tiempo];
                }
            }
            // Si es de noche, agregar prefijo "n_"
            if ($this->esDeNoche()){
                 $ubafcen_tiempo_cielo = 'n_' . $ubafcen_tiempo_cielo;
            }

            set_transient( 'ubafcen_tiempo_cielo', $ubafcen_tiempo_cielo, 10 * MINUTE_IN_SECONDS );
        }
        $this->cielo = $ubafcen_tiempo_cielo;
        return $this->cielo;
    }

    public function obtenerPronostico(){
        // Texto del pronóstico
         if ( false === ( $ubafcen_tiempo_pronostico = get_transient( 'ubafcen_tiempo_pronostico' ) ) ) {
            $respuesta_http = wp_remote_get("http://tiempo.at.fcen.uba.ar/ubapronr.htm", array('timeout' => 4));
            if (is_wp_error($respuesta_http) || !is_array($respuesta_http) || (is_array($respuesta_http) && $respuesta_http['response']['code'] >= 300 )){
                $ubafcen_tiempo_pronostico = 'Error al conectarse al sitio del DCAO';
            }
            else {
                $pagina_html = $respuesta_http['body'];
                $DOM = new DOMDocument;
                $DOM->loadHTML($pagina_html);

               // la descripción la obtengo con magia negra (al igual que con el ícono de pronóstico)
               $tds = $DOM->getElementsByTagName('td');
               $texto_pronostico = $tds->item(5)->textContent;
               $ubafcen_tiempo_pronostico = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u','',$texto_pronostico); //limpia caracteres unicode indeseables (como espacios mágicos)
               $ubafcen_tiempo_pronostico = preg_replace('/(\s)+/', ' ', $ubafcen_tiempo_pronostico);
            }

            set_transient( 'ubafcen_tiempo_pronostico', $ubafcen_tiempo_pronostico, 10 * MINUTE_IN_SECONDS );
        }
        $this->pronostico = $ubafcen_tiempo_pronostico;
        return $this->pronostico;
    }


    public function esDeNoche(){
        $hora_actual = time();

        // Coordenadas del DCAO
        $lat_em_dcao = -34.54194;
        $lon_em_dcao = -58.44;

        $puesta_de_sol = date_sunset($hora_actual, SUNFUNCS_RET_TIMESTAMP, $lat_em_dcao, $lon_em_dcao);
        $salida_de_sol = date_sunrise($hora_actual, SUNFUNCS_RET_TIMESTAMP, $lat_em_dcao, $lon_em_dcao);

        return $hora_actual < $salida_de_sol || $hora_actual >= $puesta_de_sol;
       }
}


add_action('widgets_init', create_function('', 'return register_widget("WidgetTiempoFCEN");'));


?>
