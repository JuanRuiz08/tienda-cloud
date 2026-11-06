# Cómo generar las evidencias para el informe

1. Abre Docker Desktop y espera a que diga que está en ejecución.
2. En una terminal, desde la raíz del repositorio:
   - Windows: `powershell -ExecutionPolicy Bypass -File evidencias\probar.ps1`
   - Linux/macOS: `bash evidencias/probar.sh`
3. El script deja los resultados en `evidencias/logs/`. Esos archivos de texto son los logs del informe.
4. Toma estas capturas de pantalla (cada una con la hora visible si es posible):

| N.º | Captura | Qué debe verse |
|---|---|---|
| 1 | Terminal con `docker compose ps` | Los 4 servicios en estado `running` y `healthy` |
| 2 | Navegador en http://localhost:8080 | La página de inicio de sesión de la tienda |
| 3 | Navegador, inicio de la tienda con sesión | Catálogo con imágenes de productos |
| 4 | Navegador, carrito o pedido | Un pedido realizado |
| 5 | Navegador en http://127.0.0.1:8081 | phpMyAdmin con la tabla `users` o `orders` |
| 6 | Docker Desktop, pestaña Containers | Los contenedores del proyecto `tienda` |
| 7 | Docker Desktop, pestaña Volumes | `tienda_db_data` y `tienda_uploads` |
| 8 | Terminal con `docker compose logs app` | Líneas `[app] INFO` de login y pedido |

5. Para la prueba de persistencia, antes de ejecutar el script crea un pedido a mano desde la tienda. Después del script, verifica en phpMyAdmin que sigue ahí (captura 5).
