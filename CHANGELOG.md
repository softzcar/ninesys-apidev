# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/), versionado según [SemVer](https://semver.org/lang/es/).

Ver también `ninesys-hub/releases/` para el contexto de negocio detrás de cada cambio (qué problema resolvía, qué otros repos se tocaron junto con este).

## [v1.0.9] - 2026-09-04
- Fix: ordenar alfabeticamente el typeahead de busqueda de clientes (nueva orden/presupuesto)

## [v1.0.8] - 2026-09-04
- Reporte pagos-abonos: total pagado y saldo pendiente ahora acotados al rango de fechas, no historial completo

## [v1.0.7] - 2026-09-04
- Endpoint para borrar imagenes huerfanas del editor Quill

## [v1.0.6] - 2026-09-03
- Endpoint liviano GET /produccion/ordenes-terminadas para la nueva seccion de reposiciones sobre ordenes terminadas

## [v1.0.5] - 2026-09-03
- Fix: telefono/email crudos (no json_decode) al resolver/crear cliente -- email real nunca se capturaba antes

## [v1.0.4] - 2026-09-03
- Mismo fix de presupuesto/nuevo (nombre/apellido crudos + merge sin pisar datos reales) portado a /ordenes/nueva

## [v1.0.3] - 2026-09-03
- Fix: no pisar email/datos reales del cliente al actualizar desde presupuesto (merge, no reemplazo ciego)

## [v1.0.2] - 2026-09-03
- Fix regresion: nombre/apellido crudos al crear cliente desde presupuesto (no json_decode)

## [v1.0.1] - 2026-09-03
- Resolver o crear cliente en customers desde /presupuesto/nuevo (antes se perdian telefono/email/apellido)

## [v1.0.0] - 2026-09-02
Punto de partida del sistema de versionado. No es la primera versión real de la app -- es donde arranca el control formal de versiones, tags de git y este archivo.
