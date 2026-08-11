# Flujo operativo inicial de 18dev

## Camino diario

1. Cargá manualmente cada pedido en **Tickets operativos**, preservando quién lo pidió, de dónde llegó y el texto original.
2. Hacé triage antes de implementar: aclarar objetivo, prioridad, fecha límite y cualquier definición faltante.
3. Actualizá el ticket desde su detalle. Cuando el triage esté completo, marcá su estado como **Lista**.
4. Con el proyecto ya registrado en el orquestador y el ticket en **Lista**, usá **Crear tarea de ejecución**. Esto genera una única tarea `draft`, conserva el contexto operativo en su descripción y cambia el ticket a **En implementación**.
5. Si el ticket ya tiene una tarea vinculada, seguí el enlace existente: volver a ejecutar la conversión no crea una tarea duplicada.

## Cola de atención

La navegación y ambos tableros muestran conteos locales que enlazan a sus vistas filtradas con `attention=1`. La cola operativa incluye tickets en bandeja, triage, listos para convertir, marcados como requiere atención, urgentes o con vencimiento para hoy o anterior. La cola de ejecución incluye tareas fallidas, bloqueadas, en ejecución, con revisión solicitada, con verificaciones o aceptación fallidas, y tareas completadas sin decisión humana.

Estos avisos solo ordenan el trabajo que ya existe en SQLite: no generan notificaciones del sistema ni integraciones externas.

## Alcance de este MVP

La carga inicial es manual. Email, WhatsApp y reuniones son orígenes que se registran para no perder trazabilidad, pero todavía no existen integraciones ni importaciones automáticas.

Un ticket operativo representa la conversación y la decisión de qué hacer. Una `OrchestratorTask` representa el trabajo técnico aislado, sus verificaciones y su revisión. La conversión requiere que `project_name` coincida exactamente con un proyecto registrado; si no existe, el ticket no cambia y se informa el problema para resolverlo antes de implementar.
