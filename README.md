Sistema de Inventario TecnoStock (MVP)

1. Definición del Problema y Alcance

Problema: El emprendimiento TecnoStock gestiona actualmente su catálogo de accesorios tecnológicos mediante planillas de cálculo, lo que genera códigos duplicados, inconsistencias en las cantidades disponibles y desconocimiento del stock real.
Alcance del MVP: Aplicación web ejecutable en entorno local (Apache, PHP 8 con PDO y MySQL) que centraliza el catálogo de productos, controla las entradas y salidas de inventario con actualización automática de existencias, previene quiebres o saldos negativos mediante validaciones en cliente (JavaScript) y servidor (PHP), y emite alertas visuales de stock bajo.

2. Actor del Sistema

Encargado de inventario Autenticarse en el sistema y administrar el catálogo de productos, sus categorías y el registro de movimientos de existencias (entradas y salidas).

3. Requerimientos Funcionales (RF) y Trazabilidad

RF01 Iniciar y cerrar sesión con un usuario previamente registrado y activo.
RF02 Registrar productos con código único, nombre, categoría, descripción, precio, stock actual y stock mínimo.
RF03 Consultar el listado general de productos e identificar su estado y nivel de existencias. 
RF04 Buscar y filtrar productos por nombre, código o categoría.
RF05 Modificar precio, descripción, categoría y cantidades de un producto existente. 
RF06 Desactivar productos que ya no se comercializan (baja lógica sin eliminación física).
RF07 Registrar movimientos de entrada y salida de existencias actualizando el stock automáticamente.
RF08 Mostrar una alerta visual destacada cuando el stock_actual sea menor que el stock_minimo.

4. Reglas de Negocio (RN)

RN01 (Unicidad de código): El código de un producto no puede repetirse en la base de datos (UNIQUE).
RN02 (No negatividad): El precio, el stock actual, el stock mínimo y la cantidad de un movimiento no pueden ser negativos (y la cantidad de movimiento debe ser mayor a cero).
RN03 (Trazabilidad de movimientos): Todo movimiento debe registrar obligatoriamente fecha/hora, tipo (ENTRADA o SALIDA), cantidad, producto afectado y usuario responsable.
RN04 (Control de saldo en salidas): Ninguna salida de inventario puede dejar el stock_actual bajo cero (stock_actual - cantidad >= 0).
RN05 (Baja lógica / Integridad histórica): Un producto no se elimina físicamente (DELETE); se desactiva cambiando su estado a 0 para conservar el historial de movimientos.
RN06 (Actualización atómica de stock): El stock_actual del producto se actualiza automáticamente mediante transacción inmediatamente después de cada entrada o salida válida.