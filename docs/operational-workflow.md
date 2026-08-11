# Flujo operativo inicial de 18dev

## Camino diario

1. Cargá manualmente cada pedido en **Tickets operativos**, preservando quién lo pidió, de dónde llegó y el texto original.
2. Hacé triage antes de implementar: aclarar objetivo, prioridad, fecha límite y cualquier definición faltante.
3. Recién cuando el pedido esté listo, podrá convertirse en una tarea de ejecución del orquestador.

## Alcance de este MVP

La carga inicial es manual. Email, WhatsApp y reuniones son orígenes que se registran para no perder trazabilidad, pero todavía no existen integraciones ni importaciones automáticas.

Un ticket operativo representa la conversación y la decisión de qué hacer. Una `OrchestratorTask` representa el trabajo técnico aislado, sus verificaciones y su revisión. En un slice posterior, un ticket refinado podrá alimentar la creación de una tarea de ejecución sin duplicar contexto a mano.
