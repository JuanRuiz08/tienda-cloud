<?php
// bootstrap.php (auto_prepend_file) ya registra el manejador de sesiones en BD.
session_start();

if (!isset($_SESSION['order'])) {
    header('location: inicio.php');
    exit();
}

$order = $_SESSION['order'];
$total_price = $order['total_price'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Pago con PayPal</title>
   <link rel="stylesheet" href="../css/style.css">
   <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
   <style>
       body {
           background: linear-gradient(to right, #6a11cb, #2575fc);
           color: white;
           font-family: 'Arial', sans-serif;
       }
       .payment-container {
           max-width: 600px;
           margin: 50px auto;
           padding: 30px;
           background: rgba(255, 255, 255, 0.1);
           border-radius: 15px;
           box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
           backdrop-filter: blur(5px);
           border: 1px solid rgba(255, 255, 255, 0.2);
       }
       h1 {
           font-size: 28px;
           margin-bottom: 20px;
           text-align: center;
       }
       .total-price {
           font-size: 24px;
           font-weight: bold;
           color: #ffc107;
           text-align: center;
           margin-bottom: 30px;
       }
       #paypal-button-container {
           display: flex;
           justify-content: center;
           margin-top: 20px;
       }
       .footer {
           text-align: center;
           margin-top: 50px;
           color: rgba(255, 255, 255, 0.7);
       }
   </style>
</head>
<body>
   <div class="payment-container">
       <h1>¡Estás a un paso de completar tu compra!</h1>
       <p class="total-price">Total a pagar: $<?php echo number_format($total_price, 2); ?></p>
       <!-- Asegúrate de integrar correctamente el botón de PayPal -->
       <div id="paypal-button-container"></div>
       <div class="footer">
           <p>Tu compra está protegida y garantizada por PayPal.</p>
       </div>
   </div>

   <?php $paypal_client_id = getenv('PAYPAL_CLIENT_ID') ?: ''; ?>
   <?php if ($paypal_client_id !== ''): ?>
   <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode($paypal_client_id); ?>"></script>
   <?php else: ?>
   <p>El pago con PayPal no está configurado (falta PAYPAL_CLIENT_ID).</p>
   <?php endif; ?>
   <script>
    if (typeof paypal !== 'undefined') paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: '<?php echo number_format($total_price, 2, '.', ''); ?>' // Asegúrate de usar el formato correcto
                    }
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                alert('Gracias por tu compra, ' + details.payer.name.given_name);
                // Redirigir a la página de inicio
                window.location.href = "inicio.php"; // Cambia 'inicio.php' por la ruta correcta si es necesario
            });
        }
    }).render('#paypal-button-container');
</script>

</body>
</html>
