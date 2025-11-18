<?php
session_start();

// Configuración de base de datos - AJUSTA ESTOS VALORES
$host = 'localhost';
$dbname = 'tienda_urbana';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Inicializar carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$mensaje = '';
$tipo_mensaje = 'success';
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// Agregar al carrito
if ($accion == 'agregar' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto && $producto['stock'] > 0) {
        $encontrado = false;
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id'] == $producto['id']) {
                if ($item['cantidad'] < $producto['stock']) {
                    $item['cantidad']++;
                    $encontrado = true;
                    $mensaje = "¡" . $producto['nombre'] . " agregado al carrito!";
                } else {
                    $mensaje = "No hay más stock disponible";
                    $tipo_mensaje = 'error';
                }
                break;
            }
        }
        if (!$encontrado) {
            $_SESSION['carrito'][] = [
                'id' => $producto['id'],
                'nombre' => $producto['nombre'],
                'precio' => $producto['precio'],
                'imagen' => isset($producto['imagen']) ? $producto['imagen'] : 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=400&h=400&fit=crop',
                'cantidad' => 1
            ];
            $mensaje = "¡" . $producto['nombre'] . " agregado al carrito!";
        }
    } else {
        $mensaje = "Producto sin stock";
        $tipo_mensaje = 'error';
    }
}

// Eliminar del carrito
if ($accion == 'eliminar' && isset($_GET['id'])) {
    foreach ($_SESSION['carrito'] as $key => $item) {
        if ($item['id'] == $_GET['id']) {
            unset($_SESSION['carrito'][$key]);
            $_SESSION['carrito'] = array_values($_SESSION['carrito']);
            $mensaje = "Producto eliminado del carrito";
            break;
        }
    }
}

// Vaciar carrito
if ($accion == 'vaciar') {
    $_SESSION['carrito'] = [];
    $mensaje = "Carrito vaciado";
}

// Mostrar formulario de checkout
$mostrar_checkout = false;
if ($accion == 'comprar' && !empty($_SESSION['carrito'])) {
    $mostrar_checkout = true;
}

// Finalizar compra con datos del cliente
if ($accion == 'procesar_pago' && !empty($_SESSION['carrito']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $ciudad = $_POST['ciudad'] ?? '';
    $codigo_postal = $_POST['codigo_postal'] ?? '';
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    
    if ($nombre && $email && $telefono && $direccion && $metodo_pago) {
        $total = 0;
        foreach ($_SESSION['carrito'] as $item) {
            $total += $item['precio'] * $item['cantidad'];
        }
        
        try {
            $pdo->beginTransaction();
            
            // Crear pedido con datos del cliente
            $stmt = $pdo->prepare("INSERT INTO pedidos (total, estado, cliente_nombre, cliente_email, cliente_telefono, cliente_direccion, cliente_ciudad, cliente_cp, metodo_pago) VALUES (?, 'completado', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$total, $nombre, $email, $telefono, $direccion, $ciudad, $codigo_postal, $metodo_pago]);
            $pedido_id = $pdo->lastInsertId();
            
            // Guardar detalles y actualizar stock
            foreach ($_SESSION['carrito'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_nombre, cantidad, precio) VALUES (?, ?, ?, ?)");
                $stmt->execute([$pedido_id, $item['nombre'], $item['cantidad'], $item['precio']]);
                
                // Actualizar stock
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['cantidad'], $item['id']]);
            }
            
            $pdo->commit();
            $_SESSION['ultimo_pedido'] = $pedido_id;
            $_SESSION['carrito'] = [];
            header("Location: index.php?accion=confirmacion");
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al procesar la compra: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = "Por favor completa todos los campos obligatorios";
        $tipo_mensaje = 'error';
    }
}

// Mostrar página de confirmación
$mostrar_confirmacion = false;
if ($accion == 'confirmacion' && isset($_SESSION['ultimo_pedido'])) {
    $mostrar_confirmacion = true;
    $pedido_id = $_SESSION['ultimo_pedido'];
    
    // Obtener datos del pedido
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido_datos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    unset($_SESSION['ultimo_pedido']);
}

// Obtener productos con filtro y búsqueda
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';
$busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';

if ($busqueda && $filtro) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE categoria = ? AND nombre LIKE ? AND stock > 0 ORDER BY id DESC");
    $stmt->execute([$filtro, "%$busqueda%"]);
} elseif ($busqueda) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE nombre LIKE ? AND stock > 0 ORDER BY id DESC");
    $stmt->execute(["%$busqueda%"]);
} elseif ($filtro) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE categoria = ? AND stock > 0 ORDER BY id DESC");
    $stmt->execute([$filtro]);
} else {
    $stmt = $pdo->query("SELECT * FROM productos WHERE stock > 0 ORDER BY id DESC");
}
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total del carrito
$total_carrito = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_carrito += $item['precio'] * $item['cantidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Estilo Urbano - Tienda de Ropa</title>
  <style>
    /* General */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      background-color: #f4f4f4;
      color: #333;
      transition: background-color 0.3s;
      padding: 0;
    }

    header {
      background-color: #111;
      color: #fff;
      padding: 1em;
      text-align: center;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    nav {
      background-color: #333;
      display: flex;
      justify-content: space-between;
      padding: 0.5em 2em;
      align-items: center;
      position: relative;
      flex-wrap: wrap;
    }

    nav a {
      color: #fff;
      padding: 1em;
      text-decoration: none;
      transition: background-color 0.3s ease;
    }

    nav a:hover {
      background-color: #555;
    }

    body.modo-oscuro {
      background-color: #1c1c1c;
      color: #ddd;
    }

    .modo-oscuro header, .modo-oscuro footer {
      background-color: #222;
    }

    .modo-oscuro nav {
      background-color: #444;
    }

    .modo-oscuro .producto {
      background-color: #2a2a2a;
      color: #ddd;
    }

    .modo-oscuro .carrito-contenido {
      background-color: #2a2a2a;
      color: #ddd;
    }

    .modo-oscuro .carrito-item {
      border-bottom-color: #444;
    }

    .modo-oscuro .carrito-item-nombre {
      color: #ddd;
    }

    .modo-oscuro .carrito-footer {
      border-top-color: #444;
    }

    .modo-oscuro .footer-section {
      background-color: #2a2a2a;
    }

    .modo-oscuro .checkout-form {
      background-color: #2a2a2a;
      color: #ddd;
    }

    .modo-oscuro .checkout-form input,
    .modo-oscuro .checkout-form select {
      background-color: #333;
      color: #ddd;
      border-color: #555;
    }

    .modo-oscuro .confirmacion-box {
      background-color: #2a2a2a;
      color: #ddd;
    }

    .slider {
      position: relative;
      width: 100%;
      height: 300px;
      overflow: hidden;
      margin-bottom: 2em;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 2.5em;
      font-weight: bold;
    }

    .carrito-dropdown {
      position: relative;
      display: inline-block;
    }

    .carrito-icono {
      color: #fff;
      font-size: 1.4em;
      cursor: pointer;
      padding: 0.5em 1em;
      transition: color 0.3s;
      user-select: none;
    }

    .carrito-icono:hover {
      color: #ffd700;
    }

    .carrito-contenido {
      display: none;
      position: absolute;
      right: 0;
      background-color: #fff;
      min-width: 380px;
      max-width: 450px;
      max-height: 500px;
      overflow-y: auto;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
      z-index: 1000;
      border-radius: 8px;
      padding: 0;
      margin-top: 10px;
      color: #333;
    }

    .carrito-contenido.active {
      display: block;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .carrito-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1em;
      border-radius: 8px 8px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .carrito-header h3 {
      margin: 0;
      font-size: 1.2em;
    }

    .btn-cerrar-carrito {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      font-size: 1.5em;
      cursor: pointer;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s;
    }

    .btn-cerrar-carrito:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: rotate(90deg);
    }

    .carrito-body {
      padding: 1em;
      max-height: 300px;
      overflow-y: auto;
    }

    .carrito-contenido ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .carrito-item {
      display: flex;
      gap: 1em;
      padding: 1em 0;
      border-bottom: 1px solid #eee;
      align-items: center;
    }

    .carrito-item:last-child {
      border-bottom: none;
    }

    .carrito-item-img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 6px;
      flex-shrink: 0;
    }

    .carrito-item-info {
      flex: 1;
    }

    .carrito-item-nombre {
      font-weight: bold;
      font-size: 0.95em;
      margin-bottom: 0.3em;
      color: #333;
    }

    .carrito-item-detalles {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9em;
      color: #666;
    }

    .carrito-item-cantidad {
      display: flex;
      align-items: center;
      gap: 0.5em;
      background: #f0f0f0;
      padding: 0.3em 0.6em;
      border-radius: 4px;
    }

    .carrito-item-precio {
      font-weight: bold;
      color: #28a745;
    }

    .btn-eliminar-item {
      background: #ff4444;
      border: none;
      color: white;
      font-size: 1.2em;
      cursor: pointer;
      width: 30px;
      height: 30px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.3s;
    }

    .btn-eliminar-item:hover {
      background: #cc0000;
      transform: scale(1.1);
    }

    .carrito-footer {
      padding: 1em;
      border-top: 2px solid #eee;
    }

    .total {
      margin-top: 1em;
      font-weight: bold;
      text-align: right;
      font-size: 1.2em;
      padding-top: 0.5em;
      border-top: 2px solid #333;
    }

    .btn-finalizar {
      width: 100%;
      background-color: #28a745;
      color: white;
      border: none;
      padding: 0.8em;
      margin-top: 0.5em;
      cursor: pointer;
      border-radius: 4px;
      font-weight: bold;
      font-size: 1em;
      transition: all 0.3s;
    }

    .btn-finalizar:hover {
      background-color: #218838;
      transform: scale(1.02);
    }

    .btn-vaciar {
      width: 100%;
      background-color: #dc3545;
      color: white;
      border: none;
      padding: 0.6em;
      margin-top: 0.5em;
      cursor: pointer;
      border-radius: 4px;
      font-size: 0.9em;
    }

    .btn-vaciar:hover {
      background-color: #c82333;
    }

    .producto {
      background-color: #fff;
      border-radius: 8px;
      padding: 1em;
      text-align: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      flex: 1 1 250px;
      max-width: 250px;
    }

    .producto:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .producto img {
      max-width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 4px;
    }

    .producto h2 {
      font-size: 1.2em;
      margin: 0.5em 0;
    }

    .producto .precio {
      font-size: 1.5em;
      color: #28a745;
      font-weight: bold;
      margin: 0.5em 0;
    }

    .producto .stock {
      font-size: 0.9em;
      color: #666;
      margin: 0.3em 0;
    }

    .boton-comprar {
      background-color: #000;
      color: #fff;
      border: none;
      padding: 0.6em 1.2em;
      margin-top: 1em;
      cursor: pointer;
      border-radius: 4px;
      transition: background-color 0.3s, transform 0.1s;
      font-size: 1em;
      width: 100%;
    }

    .boton-comprar:hover {
      background-color: #444;
    }

    .boton-comprar:active {
      transform: scale(0.96);
      background-color: #222;
    }

    /* Checkout Form */
    .checkout-container {
      max-width: 800px;
      margin: 2em auto;
      padding: 2em;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .checkout-form h2 {
      margin-bottom: 1.5em;
      color: #333;
      text-align: center;
    }

    .form-group {
      margin-bottom: 1.5em;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5em;
      font-weight: bold;
      color: #555;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 0.8em;
      border: 2px solid #ddd;
      border-radius: 4px;
      font-size: 1em;
      transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #667eea;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1em;
    }

    .resumen-pedido {
      background: #f8f9fa;
      padding: 1.5em;
      border-radius: 6px;
      margin-bottom: 2em;
    }

    .resumen-pedido h3 {
      margin-bottom: 1em;
      color: #333;
    }

    .resumen-item {
      display: flex;
      justify-content: space-between;
      padding: 0.5em 0;
      border-bottom: 1px solid #ddd;
    }

    .resumen-total {
      display: flex;
      justify-content: space-between;
      padding: 1em 0;
      font-size: 1.3em;
      font-weight: bold;
      color: #28a745;
      border-top: 2px solid #333;
      margin-top: 1em;
    }

    .btn-submit {
      width: 100%;
      background: #28a745;
      color: white;
      border: none;
      padding: 1em;
      font-size: 1.1em;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-submit:hover {
      background: #218838;
      transform: scale(1.02);
    }

    .btn-cancelar {
      width: 100%;
      background: #6c757d;
      color: white;
      border: none;
      padding: 0.8em;
      font-size: 1em;
      border-radius: 6px;
      cursor: pointer;
      margin-top: 1em;
      transition: all 0.3s;
    }

    .btn-cancelar:hover {
      background: #5a6268;
    }

    /* Confirmación */
    .confirmacion-box {
      max-width: 600px;
      margin: 3em auto;
      padding: 3em;
      background: white;
      border-radius: 12px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
      text-align: center;
    }

    .confirmacion-box .icono-exito {
      font-size: 5em;
      color: #28a745;
      margin-bottom: 0.5em;
    }

    .confirmacion-box h2 {
      color: #28a745;
      margin-bottom: 1em;
    }

    .confirmacion-box p {
      font-size: 1.1em;
      color: #666;
      margin: 0.5em 0;
    }

    .confirmacion-box .pedido-numero {
      font-size: 1.5em;
      font-weight: bold;
      color: #333;
      margin: 1em 0;
    }

    .confirmacion-box .btn-volver {
      display: inline-block;
      margin-top: 2em;
      padding: 1em 2em;
      background: #667eea;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      transition: all 0.3s;
    }

    .confirmacion-box .btn-volver:hover {
      background: #764ba2;
      transform: scale(1.05);
    }

    footer {
      background-color: #111;
      color: #fff;
      margin-top: 3em;
    }

    .footer-content {
      display: flex;
      justify-content: space-around;
      flex-wrap: wrap;
      padding: 2em;
      max-width: 1200px;
      margin: 0 auto;
      gap: 2em;
    }

    .footer-section {
      flex: 1;
      min-width: 250px;
    }

    .footer-section h3 {
      color: #ffd700;
      margin-bottom: 1em;
      font-size: 1.3em;
    }

    .footer-section p, .footer-section ul {
      line-height: 1.8;
      color: #ccc;
    }

    .footer-section ul {
      list-style: none;
      padding: 0;
    }

    .footer-section ul li {
      margin-bottom: 0.5em;
    }

    .footer-section a {
      color: #ccc;
      text-decoration: none;
      transition: color 0.3s;
    }

    .footer-section a:hover {
      color: #ffd700;
    }

    .social-icons {
      display: flex;
      gap: 1em;
      margin-top: 1em;
    }

    .social-icons a {
      display: inline-block;
      width: 40px;
      height: 40px;
      background-color: #333;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5em;
      transition: all 0.3s;
    }

    .social-icons a:hover {
      background-color: #ffd700;
      transform: scale(1.1);
    }

    .footer-bottom {
      background-color: #000;
      text-align: center;
      padding: 1em;
      border-top: 1px solid #333;
    }

    .filtros {
      margin: 2em;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-wrap: wrap;
      gap: 1em;
    }

    .buscador {
      width: 100%;
      max-width: 500px;
      margin: 0 auto 1em;
      padding: 0 2em;
    }

    .buscador-container {
      display: flex;
      gap: 0.5em;
      background: white;
      padding: 0.5em;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .modo-oscuro .buscador-container {
      background: #2a2a2a;
    }

    .buscador input {
      flex: 1;
      padding: 0.8em 1em;
      border: 2px solid #ddd;
      border-radius: 6px;
      font-size: 1em;
      transition: border-color 0.3s;
    }

    .buscador input:focus {
      outline: none;
      border-color: #667eea;
    }

    .modo-oscuro .buscador input {
      background: #333;
      color: #ddd;
      border-color: #555;
    }

    .btn-buscar {
      padding: 0.8em 1.5em;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1em;
      font-weight: bold;
      transition: all 0.3s;
    }

    .btn-buscar:hover {
      background: #764ba2;
      transform: scale(1.05);
    }

    .btn-limpiar {
      padding: 0.8em 1em;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1em;
      transition: all 0.3s;
    }

    .btn-limpiar:hover {
      background: #c82333;
    }

    .resultados-busqueda {
      text-align: center;
      margin: 1em 0;
      font-size: 1.1em;
      color: #666;
    }

    .modo-oscuro .resultados-busqueda {
      color: #ccc;
    }

    .filtro {
      padding: 0.5em 1em;
      cursor: pointer;
      background-color: #f0f0f0;
      border: 1px solid #ccc;
      border-radius: 4px;
      transition: background-color 0.3s;
      text-decoration: none;
      color: #333;
      display: inline-block;
    }

    .filtro:hover {
      background-color: #ddd;
    }

    .filtro.activo {
      background-color: #667eea;
      color: white;
      border-color: #667eea;
    }

    .container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
      padding: 2em;
    }

    .notificacion {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #28a745;
      color: #fff;
      padding: 1em 1.5em;
      border-radius: 6px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.3);
      z-index: 9999;
      animation: slideIn 0.3s ease;
      max-width: 400px;
    }

    .notificacion.error {
      background: #dc3545;
    }

    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .carrito-vacio {
      text-align: center;
      padding: 1em;
      color: #999;
    }

    @media (max-width: 768px) {
      nav {
        flex-direction: column;
        gap: 0.5em;
      }

      .footer-content {
        flex-direction: column;
      }

      .slider {
        height: 200px;
        font-size: 1.8em;
      }

      .buscador {
        padding: 0 1em;
      }

      .buscador-container {
        flex-direction: column;
      }

      .btn-buscar, .btn-limpiar {
        width: 100%;
      }

      .form-row {
        grid-template-columns: 1fr;
      }

      .checkout-container {
        padding: 1em;
      }

      .confirmacion-box {
        margin: 2em 1em;
        padding: 2em 1em;
      }
    }
  </style>
</head>
<body>

  <header>
    <h1>Estilo Urbano</h1>
    <p>Moda joven, urbana y con actitud</p>
  </header>

  <nav>
    <div>
      <a href="index.php">Inicio</a>
      <a href="?filtro=">Catálogo</a>
      <a href="#nosotros">Nosotros</a>
      <a href="#contacto">Contacto</a>
    </div>
    <div class="carrito-dropdown">
      <span class="carrito-icono" onclick="toggleCarrito()">🛒 Carrito (<?php echo count($_SESSION['carrito']); ?>)</span>
      <div class="carrito-contenido" id="carritoDropdown">
        <div class="carrito-header">
          <h3>🛒 Mi Carrito</h3>
          <button class="btn-cerrar-carrito" onclick="toggleCarrito()">✖</button>
        </div>
        
        <?php if (empty($_SESSION['carrito'])): ?>
          <div class="carrito-body">
            <div class="carrito-vacio">
              <p style="text-align: center; padding: 2em; color: #999;">
                😕 Tu carrito está vacío
              </p>
            </div>
          </div>
        <?php else: ?>
          <div class="carrito-body">
            <ul>
              <?php foreach ($_SESSION['carrito'] as $item): ?>
                <li class="carrito-item">
                  <?php 
                    $img_url = isset($item['imagen']) && !empty($item['imagen']) 
                      ? htmlspecialchars($item['imagen']) 
                      : 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=400&h=400&fit=crop';
                  ?>
                  <img src="<?php echo $img_url; ?>" 
                       alt="<?php echo htmlspecialchars($item['nombre']); ?>" 
                       class="carrito-item-img"
                       onerror="this.src='https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=400&h=400&fit=crop'">
                  
                  <div class="carrito-item-info">
                    <div class="carrito-item-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                    <div class="carrito-item-detalles">
                      <div class="carrito-item-cantidad">
                        <span>Cant: <?php echo $item['cantidad']; ?></span>
                      </div>
                      <div class="carrito-item-precio">
                        $<?php echo number_format($item['precio'] * $item['cantidad'], 0, ',', '.'); ?>
                      </div>
                    </div>
                  </div>
                  
                  <a href="?accion=eliminar&id=<?php echo $item['id']; ?>" style="text-decoration: none;" onclick="return confirm('¿Eliminar este producto?')">
                    <button class="btn-eliminar-item" title="Eliminar">🗑️</button>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <div class="carrito-footer">
            <p class="total">Total: $<?php echo number_format($total_carrito, 0, ',', '.'); ?></p>
            <a href="?accion=comprar" style="text-decoration:none;">
              <button class="btn-finalizar">✅ Finalizar Compra</button>
            </a>
            <a href="?accion=vaciar" style="text-decoration:none;" onclick="return confirm('¿Vaciar el carrito?')">
              <button class="btn-vaciar">🗑️ Vaciar Carrito</button>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div>
      <button onclick="toggleModoOscuro()" style="background:#555; color:#fff; border:none; padding:0.8em 1.2em; cursor:pointer; border-radius:4px;">🌙 Modo Oscuro</button>
    </div>
  </nav>

  <?php if ($mensaje): ?>
    <div class="notificacion <?php echo $tipo_mensaje == 'error' ? 'error' : ''; ?>" id="notificacion">
      <?php echo htmlspecialchars($mensaje); ?>
    </div>
  <?php endif; ?>

  <?php if ($mostrar_confirmacion): ?>
    <!-- Página de Confirmación -->
    <div class="confirmacion-box">
      <div class="icono-exito">✅</div>
      <h2>¡Compra Exitosa!</h2>
      <p>Tu pedido ha sido procesado correctamente</p>
      <div class="pedido-numero">Pedido #<?php echo $pedido_id; ?></div>
      <?php if ($pedido_datos): ?>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido_datos['cliente_nombre']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido_datos['cliente_email']); ?></p>
        <p><strong>Total:</strong> $<?php echo number_format($pedido_datos['total'], 0, ',', '.'); ?></p>
        <p><strong>Método de pago:</strong> <?php echo ucfirst(str_replace('_', ' ', $pedido_datos['metodo_pago'])); ?></p>
      <?php endif; ?>
      <p style="margin-top: 1.5em;">Recibirás un email de confirmación en breve.</p>
      <a href="index.php" class="btn-volver">🏠 Volver a la tienda</a>
    </div>

  <?php elseif ($mostrar_checkout): ?>
    <!-- Formulario de Checkout -->
    <div class="checkout-container">
      <form action="?accion=procesar_pago" method="POST" class="checkout-form">
        <h2>🛒 Finalizar Compra</h2>
        
        <div class="resumen-pedido">
          <h3>Resumen del Pedido</h3>
          <?php foreach ($_SESSION['carrito'] as $item): ?>
            <div class="resumen-item">
              <span><?php echo htmlspecialchars($item['nombre']); ?> x<?php echo $item['cantidad']; ?></span>
              <span>$<?php echo number_format($item['precio'] * $item['cantidad'], 0, ',', '.'); ?></span>
            </div>
          <?php endforeach; ?>
          <div class="resumen-total">
            <span>TOTAL:</span>
            <span>$<?php echo number_format($total_carrito, 0, ',', '.'); ?></span>
          </div>
        </div>

        <h3>Datos del Cliente</h3>
        
        <div class="form-group">
          <label for="nombre">Nombre Completo *</label>
          <input type="text" id="nombre" name="nombre" required placeholder="Ej: Juan Pérez">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required placeholder="tu@email.com">
          </div>
          <div class="form-group">
            <label for="telefono">Teléfono *</label>
            <input type="tel" id="telefono" name="telefono" required placeholder="+54 11 1234-5678">
          </div>
        </div>

        <div class="form-group">
          <label for="direccion">Dirección *</label>
          <input type="text" id="direccion" name="direccion" required placeholder="Calle, número, piso/depto">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="ciudad">Ciudad *</label>
            <input type="text" id="ciudad" name="ciudad" required placeholder="Buenos Aires">
          </div>
          <div class="form-group">
            <label for="codigo_postal">Código Postal</label>
            <input type="text" id="codigo_postal" name="codigo_postal" placeholder="1234">
          </div>
        </div>

        <div class="form-group">
          <label for="metodo_pago">Método de Pago *</label>
          <select id="metodo_pago" name="metodo_pago" required>
            <option value="">Selecciona un método</option>
            <option value="tarjeta_credito">💳 Tarjeta de Crédito</option>
            <option value="tarjeta_debito">💳 Tarjeta de Débito</option>
            <option value="mercadopago">💰 MercadoPago</option>
            <option value="transferencia">🏦 Transferencia Bancaria</option>
            <option value="efectivo">💵 Efectivo</option>
          </select>
        </div>

        <button type="submit" class="btn-submit">✅ Confirmar y Pagar</button>
        <a href="index.php" style="text-decoration: none;">
          <button type="button" class="btn-cancelar">❌ Cancelar</button>
        </a>
      </form>
    </div>

  <?php else: ?>
    <!-- Contenido Principal -->
    <div class="slider">
      ⚡ NUEVA COLECCIÓN 2025 ⚡
    </div>

    <!-- Buscador -->
    <div class="buscador">
      <form action="index.php" method="GET" class="buscador-container">
        <input 
          type="text" 
          name="buscar" 
          placeholder="🔍 Buscar productos..." 
          value="<?php echo isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : ''; ?>"
        >
        <button type="submit" class="btn-buscar">Buscar</button>
        <?php if (isset($_GET['buscar']) || isset($_GET['filtro'])): ?>
          <a href="index.php" style="text-decoration: none;">
            <button type="button" class="btn-limpiar">✖ Limpiar</button>
          </a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($busqueda): ?>
      <div class="resultados-busqueda">
        <?php if (count($productos) > 0): ?>
          Se encontraron <strong><?php echo count($productos); ?></strong> resultados para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
        <?php else: ?>
          No se encontraron resultados para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="filtros">
      <a href="?filtro=<?php echo isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''; ?>" class="filtro <?php echo $filtro == '' ? 'activo' : ''; ?>">Todos</a>
      <a href="?filtro=remera<?php echo isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''; ?>" class="filtro <?php echo $filtro == 'remera' ? 'activo' : ''; ?>">Remeras</a>
      <a href="?filtro=pantalon<?php echo isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''; ?>" class="filtro <?php echo $filtro == 'pantalon' ? 'activo' : ''; ?>">Pantalones</a>
      <a href="?filtro=campera<?php echo isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''; ?>" class="filtro <?php echo $filtro == 'campera' ? 'activo' : ''; ?>">Camperas</a>
      <a href="?filtro=accesorio<?php echo isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''; ?>" class="filtro <?php echo $filtro == 'accesorio' ? 'activo' : ''; ?>">Accesorios</a>
    </div>

    <section class="container">
      <?php if (empty($productos)): ?>
        <div style="text-align:center; width:100%; padding: 3em;">
          <h2 style="font-size: 2em; color: #999;">😕 No se encontraron productos</h2>
          <p style="font-size: 1.1em; color: #666; margin-top: 1em;">
            Intenta con otra búsqueda o explora nuestras categorías
          </p>
          <a href="index.php" style="text-decoration: none;">
            <button class="boton-comprar" style="margin-top: 1em; background: #667eea;">
              🏠 Ver todos los productos
            </button>
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($productos as $producto): ?>
          <div class="producto">
            <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" 
                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=400&h=400&fit=crop'">
            <h2><?php echo htmlspecialchars($producto['nombre']); ?></h2>
            <p class="precio">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></p>
            <p class="stock">Stock: <?php echo $producto['stock']; ?> unidades</p>
            <a href="?accion=agregar&id=<?php echo $producto['id']; ?><?php echo $busqueda ? '&buscar=' . urlencode($busqueda) : ''; ?><?php echo $filtro ? '&filtro=' . urlencode($filtro) : ''; ?>" style="text-decoration:none;">
              <button class="boton-comprar">🛒 Agregar al Carrito</button>
            </a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <footer>
    <div class="footer-content">
      <!-- Sobre Nosotros -->
      <div class="footer-section" id="nosotros">
        <h3>Sobre Nosotros</h3>
        <p>
          Somos <strong>Estilo Urbano</strong>, una tienda dedicada a ofrecer 
          las últimas tendencias en moda urbana y streetwear. Desde 2020, 
          trabajamos para que expreses tu estilo único con prendas de calidad 
          y diseños exclusivos.
        </p>
        <p style="margin-top: 1em;">
          Nuestra misión es vestir a la generación que marca tendencia, 
          ofreciendo productos accesibles sin comprometer la calidad.
        </p>
      </div>

      <!-- Contacto -->
      <div class="footer-section" id="contacto">
        <h3>Contacto</h3>
        <ul>
          <li>📍 Dirección: Av. Principal 1234, Buenos Aires</li>
          <li>📞 Teléfono: +54 11 1234-5678</li>
          <li>📧 Email: info@estilourbano.com</li>
          <li>⏰ Horario: Lun - Sáb: 10:00 - 20:00</li>
        </ul>
        <div class="social-icons">
          <a href="#" title="Facebook">📘</a>
          <a href="#" title="Instagram">📷</a>
          <a href="#" title="Twitter">🐦</a>
          <a href="#" title="WhatsApp">💬</a>
        </div>
      </div>

      <!-- Enlaces Rápidos -->
      <div class="footer-section">
        <h3>Enlaces Rápidos</h3>
        <ul>
          <li><a href="index.php">Inicio</a></li>
          <li><a href="?filtro=">Catálogo Completo</a></li>
          <li><a href="?filtro=remera">Remeras</a></li>
          <li><a href="?filtro=pantalon">Pantalones</a></li>
          <li><a href="?filtro=campera">Camperas</a></li>
          <li><a href="?filtro=accesorio">Accesorios</a></li>
          <li><a href="admin.php">Panel Admin</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; 2025 Estilo Urbano. Todos los derechos reservados.</p>
      <p style="margin-top:0.5em; font-size:0.9em;">Desarrollado con ❤️ por Estilo Urbano Team</p>
    </div>
  </footer>

  <script>
    // Toggle del carrito
    function toggleCarrito() {
      const carrito = document.getElementById('carritoDropdown');
      carrito.classList.toggle('active');
    }

    // Cerrar carrito al hacer click fuera
    document.addEventListener('click', function(event) {
      const carrito = document.getElementById('carritoDropdown');
      const carritoIcon = document.querySelector('.carrito-icono');
      
      if (carrito && !carrito.contains(event.target) && !carritoIcon.contains(event.target)) {
        carrito.classList.remove('active');
      }
    });

    function toggleModoOscuro() {
      document.body.classList.toggle("modo-oscuro");
      localStorage.setItem('modoOscuro', document.body.classList.contains('modo-oscuro'));
    }

    // Cargar preferencia
    if (localStorage.getItem('modoOscuro') === 'true') {
      document.body.classList.add('modo-oscuro');
    }

    // Ocultar notificación
    setTimeout(function() {
      const noti = document.getElementById('notificacion');
      if (noti) {
        noti.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => noti.remove(), 300);
      }
    }, 4000);

    // Scroll suave para enlaces
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({ behavior: 'smooth' });
        }
      });
    });
  </script>
</body>
</html>