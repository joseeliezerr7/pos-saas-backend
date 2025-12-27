# 🏪 POS SaaS Backend - Sistema de Punto de Venta Multi-tenant

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Sistema completo de Punto de Venta (POS) con arquitectura Multi-tenant, desarrollado con Laravel 11. Ideal para empresas que necesitan gestionar múltiples sucursales y clientes bajo una sola plataforma SaaS.

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Módulos Implementados](#-módulos-implementados)
- [Tecnologías](#-tecnologías)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [API Documentation](#-api-documentation)
- [Base de Datos](#-base-de-datos)
- [Testing](#-testing)
- [Roadmap](#-roadmap)
- [Contribuir](#-contribuir)
- [Licencia](#-licencia)

## ✨ Características

### Core Features
- 🏢 **Multi-tenant** - Aislamiento completo de datos por empresa
- 🏪 **Multi-sucursal** - Gestión de múltiples ubicaciones
- 👥 **RBAC** - Sistema robusto de roles y permisos
- 🔐 **Autenticación** - Laravel Sanctum + 2FA
- 📱 **API REST** - API completa y documentada
- 🔍 **Auditoría** - Tracking completo de todas las acciones
- 🌍 **Multi-idioma** - Preparado para internacionalización

### Funcionalidades de Negocio
- 💰 **POS Completo** - Punto de venta con múltiples métodos de pago
- 📦 **Gestión de Inventario** - Stock, ajustes, transferencias, movimientos
- 🛒 **Compras** - Órdenes de compra con workflow de aprobación
- 👤 **CRM** - Gestión de clientes y proveedores
- 💵 **Caja Registradora** - Apertura/cierre diario con tracking de transacciones
- 📊 **Reportes** - Dashboard, ventas, inventario, financieros
- 🔄 **Devoluciones** - Sistema completo de returns con reembolsos
- 📝 **Cotizaciones** - Estimados convertibles a ventas
- 🧾 **Facturación** - Generación de facturas con cumplimiento fiscal
- 💳 **Suscripciones** - Gestión de planes y billing

### Características Avanzadas
- ⚡ **Alto Rendimiento** - Optimizado para manejar alto volumen
- 🔒 **Seguridad** - Validación, sanitización, y best practices
- 📈 **Escalable** - Arquitectura preparada para crecimiento
- 🎨 **Clean Code** - PSR-12, Service Layer Pattern
- 🧪 **Testeable** - Arquitectura preparada para testing

## 📦 Módulos Implementados

### ✅ Catálogo de Productos
- Productos con variantes
- Categorías jerárquicas
- Marcas
- Unidades de medida
- Pricing y costos
- Control de impuestos
- Soporte para productos y servicios

### ✅ Inventario
- Stock por sucursal
- Ajustes de inventario con aprobación
- Transferencias entre sucursales
- Movimientos de stock
- Alertas de stock bajo
- Histórico completo

### ✅ Ventas y POS
- Interfaz de punto de venta
- Múltiples métodos de pago (efectivo, tarjeta, transferencia, crédito, QR, mixto)
- Descuentos por línea y generales
- Cotizaciones con conversión a venta
- Devoluciones completas y parciales
- Anulación de ventas

### ✅ Compras
- Órdenes de compra
- Workflow de aprobación
- Recepción de mercancía
- Integración con inventario
- Gestión de proveedores

### ✅ Fiscal y Facturación
- Facturación electrónica
- Cumplimiento SAR Honduras
- CAI (Correlative Authorization)
- Numeración correlativa
- Generación de PDF
- Envío por email

### ✅ Administración
- Multi-tenant con planes
- Gestión de suscripciones
- Usuarios y permisos granulares
- Configuración por empresa
- Gestión de sucursales
- Logs de auditoría

### ✅ Reportes
- Dashboard ejecutivo
- Reportes de ventas
- Reportes de inventario
- Reportes financieros
- Reportes fiscales (SAR)
- Top productos
- Estadísticas por sucursal

## 🛠 Tecnologías

- **Framework:** Laravel 11.x
- **PHP:** 8.2+
- **Base de Datos:** MySQL 8.0+
- **Autenticación:** Laravel Sanctum
- **Cache:** Redis (opcional)
- **Queue:** Redis / Database
- **Storage:** Local / S3 (configurable)
- **PDF:** DomPDF

### Paquetes Principales
- `laravel/sanctum` - API Authentication
- `barryvdh/laravel-dompdf` - PDF Generation
- `intervention/image` - Image Processing
- `spatie/laravel-permission` - Role & Permission Management

## 📋 Requisitos

- PHP >= 8.2
- Composer
- MySQL >= 8.0
- Node.js & NPM (para compilar assets)
- Redis (opcional, para cache y queues)

### Extensiones PHP Requeridas
- OpenSSL
- PDO
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- GD o Imagick

## 🚀 Instalación

### 1. Clonar el Repositorio

```bash
git clone https://github.com/joseeliezerr7/pos-saas-backend.git
cd pos-saas-backend
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configurar Variables de Entorno

```bash
cp .env.example .env
```

Edita el archivo `.env` con tus configuraciones:

```env
APP_NAME="POS SaaS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_saas
DB_USERNAME=root
DB_PASSWORD=

# Configuración de Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourcompany.com
MAIL_FROM_NAME="${APP_NAME}"

# Configuración de Queue (opcional)
QUEUE_CONNECTION=database
```

### 4. Generar Key de Aplicación

```bash
php artisan key:generate
```

### 5. Ejecutar Migraciones

```bash
php artisan migrate
```

### 6. Ejecutar Seeders (Datos de Prueba)

```bash
php artisan db:seed
```

Esto creará:
- Plan básico de suscripción
- Empresa de prueba
- Usuario administrador (admin@elexito.hn / password)
- Roles y permisos
- Productos de ejemplo
- CAI de prueba (Honduras)

### 7. Crear Link de Storage

```bash
php artisan storage:link
```

### 8. Iniciar Servidor de Desarrollo

```bash
php artisan serve
```

La API estará disponible en: `http://localhost:8000`

## ⚙️ Configuración

### Configuración Multi-tenant

El archivo `config/tenant.php` contiene la configuración del sistema multi-tenant:

```php
return [
    'default_plan' => 'basic',
    'trial_days' => 14,
    'features' => [
        'basic' => [
            'max_branches' => 1,
            'max_users' => 5,
            'max_products' => 500,
            'max_monthly_transactions' => 1000,
        ],
        // ... más planes
    ],
];
```

### Configuración Fiscal

El archivo `config/fiscal.php` contiene configuraciones para Honduras SAR:

```php
return [
    'sar' => [
        'enabled' => true,
        'tax_rate' => 15, // ISV 15%
        'cai_length' => 37,
    ],
];
```

## 📚 API Documentation

### Autenticación

Todas las rutas de API requieren autenticación via Bearer token (Sanctum):

```bash
# Login
POST /api/auth/login
{
    "email": "admin@example.com",
    "password": "password"
}

# Response
{
    "success": true,
    "token": "1|xxxxx...",
    "user": {...}
}
```

### Endpoints Principales

```bash
# Productos
GET    /api/products
POST   /api/products
GET    /api/products/{id}
PUT    /api/products/{id}
DELETE /api/products/{id}

# Ventas
GET    /api/sales
POST   /api/sales
GET    /api/sales/{id}
POST   /api/sales/{id}/void

# Inventario
GET    /api/stock
POST   /api/stock/adjustment
GET    /api/stock/movements

# Reportes
GET    /api/dashboard/stats
GET    /api/dashboard/sales-chart
GET    /api/reports/sales
```

Ver documentación completa en `/docs` (próximamente con Swagger).

## 🗄️ Base de Datos

### Diagrama ER

```
companies (tenants)
├── branches
├── users
├── subscriptions
├── products
├── stock
├── sales
├── purchases
└── invoices
```

### Migraciones

Total: **29 migraciones** organizadas cronológicamente desde 2024-01-01.

Las migraciones incluyen:
- Estructura multi-tenant
- Gestión de productos y categorías
- Sistema de ventas completo
- Inventario y stock
- Facturación fiscal
- Auditoría

## 🧪 Testing

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests con coverage
php artisan test --coverage

# Ejecutar tests específicos
php artisan test --filter ProductTest
```

## 🗺️ Roadmap

Ver el archivo [MODULOS_PENDIENTES.md](MODULOS_PENDIENTES.md) para el roadmap completo.

### Próximas Funcionalidades (Q1 2025)

- [ ] Sistema de Promociones y Descuentos Avanzados
- [ ] Importación/Exportación masiva de datos (CSV/Excel)
- [ ] Notificaciones por Email automatizadas
- [ ] Generación de códigos de barras
- [ ] Programa de Lealtad/Puntos
- [ ] Gift Cards
- [ ] Reportes Financieros Avanzados (P&L, Balance, Cash Flow)
- [ ] Integraciones de pago (Stripe, PayPal)

### Largo Plazo (2025-2026)

- [ ] App Móvil (React Native)
- [ ] Predicción de inventario con ML
- [ ] Multi-idioma completo
- [ ] Multi-moneda
- [ ] API Pública documentada (Swagger)
- [ ] Integraciones E-commerce (WooCommerce, Shopify)
- [ ] Módulos verticales (Restaurantes, Servicios)

## 🤝 Contribuir

Las contribuciones son bienvenidas! Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

### Guías de Contribución

- Seguir PSR-12 coding standards
- Escribir tests para nuevas funcionalidades
- Actualizar documentación
- Mantener commits atómicos y descriptivos

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**Jose Eliezer Rodriguez**
- GitHub: [@joseeliezerr7](https://github.com/joseeliezerr7)

## 🙏 Agradecimientos

- Laravel Framework
- Comunidad open source de PHP
- Todos los contribuidores

## 📞 Soporte

Para reportar bugs o solicitar features:
- Abrir un [Issue](https://github.com/joseeliezerr7/pos-saas-backend/issues)
- Email: soporte@example.com

## 📊 Estado del Proyecto

**Estado Actual:** ✅ Production Ready (85% completitud)

- ✅ Core Features: 100%
- ⚠️ Advanced Features: 40%
- ❌ Premium Features: 15%

Sistema completamente funcional y listo para producción. Módulos avanzados en desarrollo según roadmap.

---

**Desarrollado con ❤️ usando Laravel**
