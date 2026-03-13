---
description: Gestión de Cambios en Estructura de Base de Datos
---

Este workflow describe el procedimiento obligatorio para cualquier cambio en la estructura de la base de datos de las empresas de Ninesys.

### Procedimiento Obligatorio

Cualquier cambio que afecte la estructura de las bases de datos de las empresas (específicamente las bases de datos `api_emp_N` donde N es el ID de la empresa), debe seguir estos pasos:

1.  **Identificar el Cambio**: Ya sea una nueva tabla, modificación de un campo (ENUM, longitud, tipo), o eliminación de estructuras.
2.  **Actualizar Script Maestro**: El archivo [create_new_company_api_emp_N.sql](file:///home/developer/Escritorio/niesys/ninesys-apidev/public/model/create_new_company_api_emp_N.sql) DEBE actualizarse inmediatamente con el mismo cambio. Este archivo se usa para crear nuevas empresas y debe estar siempre sincronizado con la estructura actual.
3.  **Aplicar en VPS (Contabo/Hostinger)**: Los cambios deben aplicarse en las bases de datos existentes en los servidores VPS correspondientes, asegurando que no se pierdan datos y que el ENUM o estructura sea idéntica a la del script maestro.
4.  **Documentar en GEMINI.md**: Si el cambio es significativo o introduce una nueva lógica de negocio, se debe dejar constancia en la documentación persistente.

> [!IMPORTANT]
> Omitir la actualización del archivo `create_new_company_api_emp_N.sql` provocará que las nuevas empresas se creen con una estructura obsoleta, causando errores en la aplicación.
