<?php
/*
Plugin Name: Secure Travel Reservas
Description: Sistema de reservas y pagos para Secure Travel Cartagena
Version: 1.0
Author: Sebastian Perez
*/

// Registrar Custom Post Type: Habitaciones
function str_registrar_habitaciones() {

    $labels = array(
        'name'          => 'Habitaciones',
        'singular_name' => 'Habitación',
        'add_new'       => 'Agregar nueva',
        'add_new_item'  => 'Agregar nueva habitación',
        'edit_item'     => 'Editar habitación',
        'new_item'      => 'Nueva habitación',
        'view_item'     => 'Ver habitación',
        'search_items'  => 'Buscar habitaciones',
        'menu_name'     => 'Habitaciones'
    );

    $args = array(
        'labels'        => $labels,
        'public'        => true,
        'menu_icon'     => 'dashicons-building',
        'supports'      => array('title'),
        'has_archive'   => true,
    );

    register_post_type('habitaciones', $args);
}

add_action('init', 'str_registrar_habitaciones');

// Meta box para datos de la habitación
function str_habitacion_meta_box() {
    add_meta_box(
        'str_habitacion_datos',
        'Datos de la Habitación',
        'str_habitacion_meta_box_html',
        'habitaciones',
        'normal',
        'default'
    );
}

add_action('add_meta_boxes', 'str_habitacion_meta_box');

function str_habitacion_meta_box_html($post) {

    $tipo   = get_post_meta($post->ID, 'tipo_habitacion', true);
    $precio = get_post_meta($post->ID, 'precio_habitacion', true);
    $estado = get_post_meta($post->ID, 'estado_habitacion', true);
    ?>

    <p>
        <label><strong>Tipo de habitación</strong></label><br>
        <select name="tipo_habitacion">
            <option value="">Seleccione</option>
            <option value="Sencilla" <?php selected($tipo, 'Sencilla'); ?>>Sencilla</option>
            <option value="Doble" <?php selected($tipo, 'Doble'); ?>>Doble</option>
            <option value="Familiar" <?php selected($tipo, 'Familiar'); ?>>Familiar</option>
        </select>
    </p>

    <p>
        <label><strong>Precio por noche</strong></label><br>
        <input type="number" name="precio_habitacion" value="<?php echo esc_attr($precio); ?>" />
    </p>

    <p>
        <label><strong>Estado</strong></label><br>
        <select name="estado_habitacion">
            <option value="Disponible" <?php selected($estado, 'Disponible'); ?>>Disponible</option>
            <option value="Reservada" <?php selected($estado, 'Reservada'); ?>>Reservada</option>
        </select>
    </p>

    <?php
}

function str_guardar_datos_habitacion($post_id) {

    if (array_key_exists('tipo_habitacion', $_POST)) {
        update_post_meta($post_id, 'tipo_habitacion', sanitize_text_field($_POST['tipo_habitacion']));
    }

    if (array_key_exists('precio_habitacion', $_POST)) {
        update_post_meta($post_id, 'precio_habitacion', sanitize_text_field($_POST['precio_habitacion']));
    }

    if (array_key_exists('estado_habitacion', $_POST)) {
        update_post_meta($post_id, 'estado_habitacion', sanitize_text_field($_POST['estado_habitacion']));
    }
}

add_action('save_post', 'str_guardar_datos_habitacion');


class STC_Pasarela_Pagos {

    public static function procesar_pago($monto) {
        $aprobado = (bool) rand(0, 1); 
        if ($monto <= 0) {
            return ['exito' => false, 'mensaje' => 'Monto inválido (0)'];
        }

        if ($aprobado) {
            return [
                'exito' => true,
                'transaccion_id' => 'TX-' . strtoupper(uniqid()), // Genera ID único tipo TX-A1B2...
                'mensaje' => 'Transacción Aprobada'
            ];
        } else {
            return [
                'exito' => false,
                'mensaje' => 'Fondos insuficientes / Rechazada por el banco'
            ];
        }
    }
}


function str_registrar_reservas() {
    $labels = array(
        'name'          => 'Reservas',
        'singular_name' => 'Reserva',
        'menu_name'     => 'Reservas',
        'add_new'       => 'Nueva Reserva',
        'edit_item'     => 'Editar Reserva'
    );

    $args = array(
        'labels'      => $labels,
        'public'      => false, 
        'show_ui'     => true,  
        'menu_icon'   => 'dashicons-calendar-alt',
        'supports'    => array('title'), 
    );

    register_post_type('reservas', $args);
}
add_action('init', 'str_registrar_reservas');


function str_reserva_meta_box() {
    add_meta_box(
        'str_reserva_datos',
        'Gestión de Reserva y Pagos',
        'str_reserva_meta_box_html',
        'reservas',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'str_reserva_meta_box');

function str_reserva_meta_box_html($post) {
    
    $habitacion_id = get_post_meta($post->ID, 'habitacion_id', true);
    $fecha_inicio  = get_post_meta($post->ID, 'fecha_inicio', true);
    $fecha_fin     = get_post_meta($post->ID, 'fecha_fin', true);
    $total         = get_post_meta($post->ID, 'total_reserva', true);

    
    $nombre        = get_post_meta($post->ID, 'nombre_cliente', true);
    $documento     = get_post_meta($post->ID, 'documento_cliente', true);
    $tipo_cliente  = get_post_meta($post->ID, 'tipo_cliente', true);
    $huespedes     = get_post_meta($post->ID, 'cantidad_huespedes', true);
    
    
    $estado_pago   = get_post_meta($post->ID, 'estado_pago', true);
    $info_banco    = get_post_meta($post->ID, 'info_banco', true); 

    
    $habitaciones = get_posts(array('post_type' => 'habitaciones', 'numberposts' => -1));
    ?>

    <div style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <h4>Detalles Estancia</h4>
            <p>
                <label>Habitación:</label><br>
                <select name="habitacion_id" style="width:100%">
                    <option value="">Seleccione...</option>
                    <?php foreach ($habitaciones as $h) : ?>
                        <option value="<?php echo $h->ID; ?>" <?php selected($habitacion_id, $h->ID); ?>>
                            <?php echo esc_html($h->post_title); ?> 
                            ($<?php echo get_post_meta($h->ID, 'precio_habitacion', true); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label>Entrada:</label> <input type="date" name="fecha_inicio" value="<?php echo esc_attr($fecha_inicio); ?>">
                <label>Salida:</label> <input type="date" name="fecha_fin" value="<?php echo esc_attr($fecha_fin); ?>">
            </p>
            
            <?php if($total): ?>
                <div style="background: #e5f5fa; padding: 10px; border: 1px solid #00a0d2;">
                    <strong>TOTAL A PAGAR: $<?php echo number_format($total, 0); ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <div style="flex: 1; border-left: 1px solid #ccc; padding-left: 20px;">
            <h4>Cliente</h4>
            <p><input type="text" name="nombre_cliente" placeholder="Nombre Completo" value="<?php echo esc_attr($nombre); ?>" style="width:100%"></p>
            <p><input type="text" name="documento_cliente" placeholder="Documento ID" value="<?php echo esc_attr($documento); ?>" style="width:100%"></p>
            <p>
                Tipo: 
                <select name="tipo_cliente">
                    <option value="Esporadico" <?php selected($tipo_cliente, 'Esporadico'); ?>>Esporádico</option>
                    <option value="Frecuente" <?php selected($tipo_cliente, 'Frecuente'); ?>>Frecuente</option>
                </select>
                Huéspedes: <input type="number" name="cantidad_huespedes" value="<?php echo esc_attr($huespedes); ?>" style="width: 50px;">
            </p>
        </div>
    </div>

    <hr>
    
    <div style="background: #fff8e5; padding: 15px; border: 1px solid #orange;">
        <h3>💰 Pasarela de Pagos</h3>
        <p>
            <label><strong>Estado Actual:</strong></label>
            <select name="estado_pago">
                <option value="Pendiente" <?php selected($estado_pago, 'Pendiente'); ?>>Pendiente</option>
                <option value="Procesar Pago" <?php selected($estado_pago, 'Procesar Pago'); ?>>🛑 PROCESAR PAGO (Cobrar ahora)</option>
                <option value="Pagado" <?php selected($estado_pago, 'Pagado'); ?>>✅ Pagado (Aprobado)</option>
                <option value="Rechazado" <?php selected($estado_pago, 'Rechazado'); ?>>❌ Rechazado</option>
            </select>
        </p>
        
        <?php if ($info_banco): ?>
            <p style="font-family: monospace; color: #555;">
                <strong>Última respuesta del Banco:</strong><br>
                <?php echo esc_html($info_banco); ?>
            </p>
        <?php endif; ?>
        
        <p><em>* Selecciona "PROCESAR PAGO" y guarda para simular la transacción.</em></p>
    </div>


    <?php
}


function str_procesar_reserva($post_id) {
   
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (get_post_type($post_id) !== 'reservas') return;

    
    $campos = ['habitacion_id', 'fecha_inicio', 'fecha_fin', 'nombre_cliente', 'documento_cliente', 'tipo_cliente', 'cantidad_huespedes'];
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            update_post_meta($post_id, $campo, sanitize_text_field($_POST[$campo]));
        }
    }

    
    $habitacion_id = isset($_POST['habitacion_id']) ? intval($_POST['habitacion_id']) : get_post_meta($post_id, 'habitacion_id', true);

   
    $total = 0;
    if ($habitacion_id && !empty($_POST['fecha_inicio']) && !empty($_POST['fecha_fin'])) {
        $precio_hab = (int) get_post_meta($habitacion_id, 'precio_habitacion', true);
        $d1 = new DateTime($_POST['fecha_inicio']);
        $d2 = new DateTime($_POST['fecha_fin']);
        $dias = $d1->diff($d2)->days;
        if ($dias < 1) $dias = 1;
        $total = $dias * $precio_hab;
        update_post_meta($post_id, 'total_reserva', $total);
    }

    
    $accion_usuario = isset($_POST['estado_pago']) ? $_POST['estado_pago'] : '';
    
    
    $estado_final_reserva = $accion_usuario; 

    
    if ($accion_usuario === 'Procesar Pago') {
        
        //simulación
        $resultado = STC_Pasarela_Pagos::procesar_pago($total);

        if ($resultado['exito']) {
            // PAGO EXITOSO
            $estado_final_reserva = 'Pagado';
            update_post_meta($post_id, 'info_banco', 'APROBADO ID: ' . $resultado['transaccion_id']);
        } else {
            // PAGO RECHAZADO
            $estado_final_reserva = 'Rechazado';
            update_post_meta($post_id, 'info_banco', 'RECHAZADO: ' . $resultado['mensaje']);
        }
    }

    // (Pagado, Rechazado o Pendiente)
    update_post_meta($post_id, 'estado_pago', $estado_final_reserva);


    
    if ($habitacion_id) {
        if ($estado_final_reserva === 'Pagado') {
            
            update_post_meta($habitacion_id, 'estado_habitacion', 'Reservada');
        } else {
            
            update_post_meta($habitacion_id, 'estado_habitacion', 'Disponible');
        }
    }
}
add_action('save_post', 'str_procesar_reserva');
add_action('save_post', 'str_procesar_reserva');

function str_columnas_habitaciones($columns) {
    $columns['precio'] = 'Precio';
    $columns['estado'] = 'Estado Actual';
    return $columns;
}
add_filter('manage_habitaciones_posts_columns', 'str_columnas_habitaciones');


function str_llenar_columnas_habitaciones($column, $post_id) {
    if ($column === 'precio') {
        echo '$' . number_format(get_post_meta($post_id, 'precio_habitacion', true));
    }
    if ($column === 'estado') {
        $estado = get_post_meta($post_id, 'estado_habitacion', true);
        $color = ($estado === 'Disponible') ? 'green' : 'red';
        echo '<strong style="color:'.$color.';">' . esc_html($estado) . '</strong>';
    }
}
add_action('manage_habitaciones_posts_custom_column', 'str_llenar_columnas_habitaciones', 10, 2);


function str_dashboard_widget_informe() {
    wp_add_dashboard_widget(
        'str_informe_ocupacion',        
        '🏨 Informe de Ocupación Hotelera', 
        'str_mostrar_informe_dashboard'  
    );
}
add_action('wp_dashboard_setup', 'str_dashboard_widget_informe');

function str_mostrar_informe_dashboard() {
   
    $habitaciones = get_posts(array(
        'post_type' => 'habitaciones',
        'numberposts' => -1
    ));

    $total = count($habitaciones);
    $disponibles = 0;
    $reservadas = 0;

    foreach ($habitaciones as $h) {
        $estado = get_post_meta($h->ID, 'estado_habitacion', true);
        if ($estado === 'Reservada') {
            $reservadas++;
        } else {
            $disponibles++; 
        }
    }

    
    ?>
    <div style="text-align: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
        <h2>Total Habitaciones: <?php echo $total; ?></h2>
    </div>
    <table style="width:100%; text-align: left;">
        <tr style="color: green; font-size: 1.2em;">
            <td>🟢 Disponibles:</td>
            <td style="text-align: right;"><strong><?php echo $disponibles; ?></strong></td>
        </tr>
        <tr style="color: red; font-size: 1.2em;">
            <td>🔴 Reservadas:</td>
            <td style="text-align: right;"><strong><?php echo $reservadas; ?></strong></td>
        </tr>
    </table>
    <p style="text-align:center; margin-top:15px;">
        <a href="<?php echo admin_url('edit.php?post_type=habitaciones'); ?>" class="button button-primary">Gestionar Habitaciones</a>
    </p>
    <?php

}
