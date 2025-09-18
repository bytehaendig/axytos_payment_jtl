# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## JTL Shop

This is a JTL-Shop plugin - a shop system written in PHP.
The source code for JTL-Shop can be accessed with the `reference` MCP server under `/reference/jtl-shop`.
Documentation for JTL-Shop for plugin developers is at https://jtl-devguide.readthedocs.io/projects/jtl-shop/de/latest/index.html .
Use the JTL-Shop source code to understand how it works and how it interacts with plugins.

## UI Framework

JTL Shop uses **Bootstrap 4** as its primary UI framework:

- **Admin Interface**: Uses "Bootstrap Admin for JTL Shop" template with Bootstrap components (bootstrap.js, bootstrap-ladda, bootstrap-notify, bootstrap-select, etc.)
- **Frontend (NOVA Template)**: Explicitly declares `<Framework>Bootstrap4</Framework>` in template.xml and includes bootstrap.bundle.min.js
- **Additional Libraries**: Includes jQuery UI, Font Awesome, various Bootstrap extensions, and other UI components like TinyMCE, CodeMirror, and Slick carousel

The framework provides responsive design, components, and utilities throughout both admin and customer-facing interfaces.

## Project Overview

This is the Axytos Payment Plugin for JTL Shop - a payment integration that provides "pay later" functionality through the Axytos payment provider. The plugin is built for JTL Shop version 5.0.0+ and implements a full payment workflow including precheck, confirmation, invoice creation, shipping notifications, and order management.

## Common Development Commands

### Code Quality
- **Lint code**: `vendor/bin/phpcs --standard=phpcs.xml`
- **Apply PSR-12 coding standards**: Plugin follows PSR-12 with 4-space indentation

### Dependencies
- **Install dependencies**: `composer install`
- **Update dependencies**: `composer update`

### Database Access
- **Read database**: `ddev mysql -e "COMMAND"` - **READ-ONLY ACCESS ONLY!** Never use for INSERT/UPDATE/DELETE operations
- Main database name: `db`
- Example: `ddev mysql -e "SELECT COUNT(*) FROM axytos_actions;"`

Note: No automated testing framework is currently configured in this codebase.

## Plugin Routes (JTL Shop 5.2.0+)

### Route Registration
- Register routes via `HOOK_ROUTER_PRE_DISPATCH` hook in Bootstrap.php
- Use `Router::addRoute($path, $callback, $name, $methods, $middleware)` method
- Callbacks must return `ResponseInterface` (use `JsonResponse` or `$smarty->getResponse()`)

### Route Parameters
- Dynamic segments: `/automation/{id:number}` or `/automation/{action:word}`
- Optional parameters: `/automation[/{id}]`
- Parameters passed in `$args` array to callback

### Callback Signature
```php
function (ServerRequestInterface $request, array $args, JTLSmarty $smarty) {
    // $request: HTTP request object
    // $args: Route parameters
    // $smarty: Template renderer
    return new JsonResponse(['data' => 'response']);
}
```

### URL Generation
- Generate URLs: `$router->getNamedPath('routeName', ['param' => 'value'])`
- Route names: `'routeName' + HTTP method` (e.g., `'myRouteGET'`)

### Frontend Folder Structure
- Place controllers in `/frontend/` directory
- Use controller classes with `getPath()` and `getResponse()` methods
- Register in Bootstrap via factory method pattern

### Example Implementation
```php
// In Bootstrap.php
$dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args) {
    $router = $args['router'];
    $controller = $this->createAutomationController();
    $router->addRoute($controller->getPath(), [$controller, 'getResponse'], 'automation');
});
```

### Implemented Endpoints

#### Agreement Endpoint
- **Path**: `/axytos-agreement`
- **Methods**: GET
- **Purpose**: Displays Axytos payment terms and conditions
- **Controller**: `AgreementController`
- **Response**: HTML template with agreement text

#### Update Invoice IDs Endpoint
- **Path**: `/axytos/v1/invoice-ids`
- **Methods**: POST
- **Purpose**: REST API endpoint for updating invoice IDs
- **Controller**: `ApiInvoiceIdsController`
- **Request Body**: JSON with `invoice_ids` object
- **Response**: JSON with success status and update results
- **Example Request**:
  ```json
  {
    "invoice_ids": {
      "INV001": "NEW001",
      "INV002": "NEW002"
    }
  }
  ```
- **Example Response**:
  ```json
  {
    "success": true,
    "data": {
      "updated_count": 2,
      "errors": [],
      "total_processed": 2
    }
  }
  ```

This routing approach is the official JTL Shop method and should be used instead of standalone PHP files for better integration and maintainability.

## Date/Time Formatting & Localization

### Date Formatting in Templates

**✅ PREFERRED - Use custom germanDate modifier (Axytos plugin):**
```smarty
{$timestamp|germanDate}                    <!-- German: 12. Sep 2025 14:30 -->
{$timestamp|germanDate:false}              <!-- Date only: 12. Sep 2025 -->
{$timestamp|germanDate:true:true}          <!-- With seconds: 12. Sep 2025 14:30:45 -->
```

**✅ ALTERNATIVE - Use date modifier with PHP date format and strtotime:**
```smarty
{"d. M Y H:i"|date:{$timestamp|strtotime}}     <!-- German: 12. Sep 2025 14:30 -->
{"d. M Y"|date:{$timestamp|strtotime}}         <!-- Date only: 12. Sep 2025 -->
{"d. M Y H:i:s"|date:{$timestamp|strtotime}}   <!-- With seconds: 12. Sep 2025 14:30:45 -->
```

**❌ WRONG - Avoid date_format with strftime patterns:**
```smarty
{* Less common pattern, use the date modifier instead *}
{$timestamp|date_format:"%d.%m.%Y %H:%M"}
```

**❌ WRONG - Avoid string_date_format:**
```smarty
{* Causes fatal errors due to missing smarty_make_timestamp() function *}
{$timestamp|string_date_format}
```

**Custom germanDate Modifier:**
The `germanDate` modifier is registered in Bootstrap.php and provides:
- **Parameters**: `germanDate:includeTime:includeSeconds`
- **Default**: `germanDate` = `germanDate:true:false` (date + time, no seconds)
- **Handles null/empty values**: Returns "-" for empty timestamps
- **Auto-conversion**: Accepts both datetime strings and Unix timestamps

**Key Points:**
- Prefer the custom `germanDate` modifier for cleaner, more readable templates
- Use `data-order="{$timestamp|strtotime}"` for DataTables sorting
- The format follows German conventions used across JTL plugins

### Secondary Sorting for Timestamps

**Always use primary key as secondary sort for consistent ordering:**
```php
// When multiple entries have same timestamp
$result = $this->db->getCollection(
    "SELECT * FROM table ORDER BY dProcessedAt DESC, kPrimaryKey DESC",
    $params
);
```

## Security Guidelines

### SQL Query Security - CRITICAL

**🚨 NEVER use string concatenation for SQL queries - this creates SQL injection vulnerabilities!**

**❌ WRONG - Vulnerable to SQL injection:**
```php
$sql = "SELECT * FROM table WHERE id = $id AND name = '$name'";
$sql = "SELECT * FROM table WHERE count > " . $maxCount;
```

**✅ CORRECT - Use parameterized queries:**
```php
$sql = "SELECT * FROM table WHERE id = :id AND name = :name";
$result = $this->db->getCollection($sql, ['id' => $id, 'name' => $name]);

$sql = "SELECT * FROM table WHERE count > :maxCount";
$result = $this->db->getSingleObject($sql, ['maxCount' => $maxCount]);
```

**Key principles:**
- All user inputs MUST be parameterized
- Use named placeholders (`:parameter`) in queries
- Pass parameters as associative arrays
- Never concatenate variables directly into SQL strings
- This applies to ALL query types: SELECT, INSERT, UPDATE, DELETE

**JTL Database Methods with Parameters:**
- `$db->getCollection($sql, $params)` - Returns Collection of objects
- `$db->getSingleObject($sql, $params)` - Returns single object or null
- `$db->getObjects($sql, $params)` - Returns array of objects  
- `$db->getArrays($sql, $params)` - Returns array of associative arrays
- `$db->getSingleArray($sql, $params)` - Returns single associative array
- `$db->getAffectedRows($sql, $params)` - Returns number of affected rows
- `$db->queryPrepared($sql, $params, $returnType)` - Generic prepared query method
- `$db->select()`, `$db->selectAll()` - Use for simple WHERE conditions with arrays
- `$db->insert()`, `$db->update()`, `$db->delete()` - Pass data as objects/arrays for table operations

**Parameter Binding:**
- Use named parameters: `:paramName` in SQL, `['paramName' => $value]` in params array
- All parameters are automatically escaped and type-checked by PDO
- Never use `$db->quote()` or `$db->escape()` with parameterized queries

## High-Level Architecture

### Core Components

1. **Bootstrap.php** - Main plugin bootstrap class that:
   - Registers event hooks for order status updates and payment processing
   - Handles admin notifications and frontend agreement links
   - Manages cron job registration for payment updates
   - Coordinates between JTL Shop core and the payment method

2. **AxytosPaymentMethod** - Main payment method implementation:
   - Extends JTL's base `Method` class
   - Handles the complete payment workflow: precheck → confirmation → invoice → shipping
   - Manages encrypted API key storage and plugin settings
   - Implements payment validation and order status management

3. **ApiClient** - HTTP client for Axytos API:
   - Handles all communication with Axytos sandbox/production APIs
   - Manages authentication via X-API-Key header
   - Implements endpoints: precheck, orderConfirm, createInvoice, updateShippingStatus, cancelOrder

4. **DataFormatter** - Data transformation layer:
   - Converts JTL order objects to Axytos API format
   - Handles address formatting, product categorization, and pricing calculations
   - Ensures data consistency between precheck and confirm calls (critical requirement)

### Payment Flow

1. **Precheck Phase** (`preparePaymentProcess`):
   - Customer selects Axytos payment method
   - Order data is formatted and sent to Axytos for approval
   - Order data is cached to ensure identical data in confirm phase
   - Customer is redirected based on approval/rejection

2. **Confirmation Phase** (`handleNotification`):
   - Order is saved to database with generated order number
   - Cached order data is sent to Axytos for final confirmation
   - Order status is updated to "in processing"
   - Order attributes are stored for tracking

3. **Post-Order Management**:
   - Invoice creation when order is shipped (`orderWasShipped`)
   - Shipping status updates to Axytos
   - Order cancellation and reactivation support

### Admin Interface

The admin interface provides comprehensive monitoring and management tools across multiple tabs:

- **API Setup Tab**: Configuration of API key and sandbox/production mode
- **Status Tab**: Real-time monitoring and action processing dashboard  
- **Invoices Tab**: Invoice management and overview (placeholder functionality)
- **Development Tab**: Development tools (only visible in dev mode)
- Settings are encrypted using JTL's XTEA encryption service

#### Admin Menu Handler Architecture

Admin tabs are managed through a centralized handler system in Bootstrap.php:

**Bootstrap.php `renderAdminMenuTab()` method:**
- Centralized Smarty setup for all handlers via `setupSmartyForAdmin()`
- Handles gettext localization and plugin registration
- Routes tab requests to appropriate handler classes
- Registers `__()` and `sprintf` modifiers for template translations

**Handler Pattern:**
All admin handlers follow a consistent pattern:
```php
class ExampleHandler 
{
    private PluginInterface $plugin;
    private AxytosPaymentMethod $method;
    private DbInterface $db;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db) {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
    }

    public function render(string $tabName, int $menuID, JTLSmarty $smarty): string {
        // Form handling and business logic
        // Template variable assignment
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/example.tpl');
    }
}
```

**Current Handlers:**
- `SetupHandler` - API configuration and settings
- `StatusHandler` - Order monitoring and action management
- `InvoicesHandler` - Invoice management (basic implementation)
- `DevHandler` - Development tools (dev mode only)

**Template Structure:**
- Templates located in `adminmenu/template/` directory
- Bootstrap 4 styling for consistency with JTL Shop admin
- CSRF token support via `Form::getTokenInput()`
- Translation support using `__()` modifier
- Responsive design with card-based layouts

**Adding New Admin Tabs:**
1. Create handler class in `adminmenu/` directory
2. Create template file in `adminmenu/template/` directory  
3. Add handler import to Bootstrap.php
4. Add tab condition in `renderAdminMenuTab()` method
5. Add `<Customlink>` entry to info.xml with appropriate sort order

#### Status Handler (`adminmenu/StatusHandler.php`)
The StatusHandler provides comprehensive order and action monitoring through the admin interface:

**Key Components:**
- **Status Overview**: Real-time dashboard showing pending orders, broken actions, total orders, and cron job status
- **Action Processing**: Manual trigger for processing pending/retryable actions via "Process All" button
- **Order Search**: Detailed order lookup by ID or order number with full action history
- **Cron Management**: Monitoring and reset functionality for stuck cron jobs
- **Broken Action Management**: Tools to retry or remove permanently failed actions

**Critical Logic:**
- **Action Status Determination (using new schema)**:
  - `bDone = FALSE` with no failures (`dFailedAt IS NULL`) = truly pending
  - `bDone = FALSE` with `dFailedAt` but `nFailedCount <= MAX_RETRIES` = retryable
  - `bDone = FALSE` with `nFailedCount > MAX_RETRIES` = permanently broken
  - `bDone = TRUE` = completed
- **Action Processing**: Individual actions are processed separately; broken actions are skipped but don't prevent processing of retryable actions in the same order
- **Cron Status Detection**: Jobs running >2 hours are considered "stuck"

#### Status Template (`adminmenu/template/status.tpl`)
The template provides a responsive admin dashboard with:
- **Status Cards**: Visual overview of system health (cron, pending, broken actions)
- **Conditional UI**: Reset buttons only appear when needed (e.g., stuck cron jobs)
- **Unified Actions Table**: Shows orders with detailed action breakdowns (pending/retry/broken columns)
- **Interactive Elements**: Click-to-search orders, confirmation dialogs for destructive actions
- **Bootstrap Styling**: Consistent with JTL Shop admin interface patterns

#### Database Schema Interactions
The status system queries several key tables:
- `axytos_actions`: Main actions table with status tracking (uses `bDone` boolean + `nFailedCount` for status determination)
- `axytos_actionslog`: Detailed logging for troubleshooting
- `tjobqueue`: JTL's cron job queue for monitoring stuck jobs
- `tcron`: Cron job definitions and scheduling
- `tbestellung`: Order data integration

#### Database Schema

**axytos_actions table:**
```sql
kAxytosAction (int PK auto_increment) - Unique action ID
kBestellung (int FK) - Order ID reference to tbestellung.kBestellung
cAction (varchar(50)) - Action type: 'precheck', 'confirm', 'invoice', 'shipped', 'cancel'
dCreatedAt (datetime) - When action was created
dFailedAt (datetime nullable) - When action last failed (NULL if never failed)
nFailedCount (int default 0) - Number of retry attempts
cFailReason (text nullable) - Last failure reason
dProcessedAt (datetime nullable) - When action was successfully completed
cData (longtext nullable) - Serialized action data
bDone (boolean default 0) - Whether action completed successfully
```

**axytos_actionslog table:**
```sql
kAxytosActionsLog (int PK auto_increment) - Unique log entry ID
kBestellung (int FK) - Order ID reference
cAction (varchar(50)) - Action type being logged
dProcessedAt (datetime) - When log entry was created
cLevel (enum: debug,info,warning,error) - Log level
cMessage (text) - Log message content
```

**tbestellung table (key fields):**
```sql
kBestellung (int PK auto_increment) - Order ID
kKunde (int FK) - Customer ID
cBestellNr (varchar(20)) - Order number
fGesamtsumme (double) - Total order amount
dVersandDatum (date nullable) - Shipping date
cZahlungsartName (varchar(255)) - Payment method name
```

**Action Status Logic:**
- Status is determined by `ActionHandler->getStatus()` method using `bDone` and `nFailedCount` fields
- Centralized status logic prevents inconsistencies between database queries and business logic

#### Common Admin Tasks
- **"Process All" Button**: Processes all retryable actions across all orders
- **Order Search**: Deep-dive into specific order issues with action logs
- **Cron Reset**: Unstick hung background jobs by resetting `isRunning` flags
- **Action Management**: Retry broken actions or remove them entirely
- **Status Monitoring**: Real-time view of system health and processing queues

#### Development Notes
- All admin actions use CSRF tokens for security
- Error handling provides user-friendly messages with technical details logged
- The interface gracefully handles edge cases (no data, failed operations)
- Confirmation dialogs prevent accidental destructive operations
- The status system is designed to be self-diagnostic for troubleshooting

This admin interface serves as the primary tool for monitoring and maintaining the Axytos payment integration's health and resolving processing issues.

### Key Integration Points

- **Order Status Hooks**: `HOOK_BESTELLUNGEN_XML_BESTELLSTATUS` for shipping notifications
- **Frontend Hooks**: `HOOK_SMARTY_OUTPUTFILTER` for agreement link injection
- **Cron Jobs**: Background processing for payment status updates (via `CronHelper`)
- **Localization**: Multi-language support (German/English) defined in `info.xml`

### Data Consistency Requirements

The plugin implements a critical workaround for a JTL Shop bug where order data changes between precheck and confirmation phases. The solution:
1. Cache order data after precheck formatting
2. Reuse identical cached data in confirmation phase
3. Log warnings if cached data is missing and regeneration is required

This ensures Axytos receives identical data in both API calls, which is a strict requirement of their system.

## Template Partials

The plugin uses a structured approach to organize reusable template components:

### Directory Structure
- **Main templates**: `adminmenu/template/` - Page-level templates
- **Partials**: `adminmenu/template/partials/` - Reusable template components

### Naming Conventions
- **Partials**: Use descriptive names without underscore prefix (e.g., `processing_details.tpl`)
- **Main templates**: Follow existing naming patterns (e.g., `invoices.tpl`, `status.tpl`)

### Include Syntax
```smarty
{include file="./partials/partial_name.tpl" variable=$value}
```

### Best Practices
- Place reusable template components in `partials/` subdirectory
- Use descriptive names that reflect the component's purpose
- Pass data via template variables rather than global scope
- Maintain consistent styling with Bootstrap 4 framework
- Include error handling for missing or empty data

## Sister Project

A sister project axytos-woocommerce exists - it is an Axytos payment plugin for WooCommerce with the same functionality. In the long run, there should be feature parity between both plugins.
Use the `reference` MCP server under the path `/reference/axytos-woocommerce`.

## MCP servers 

### reference 

- `/reference/jtl-shop`: the source code of the JTL shop. Use it to understand how the shop system works and interacts with plugins
- `/reference/axytos-woocommerce`: the source code of the sister project, which implements same functionality for WooCommerce
- `/reference/other-plugins`: some existing plugins for JTL-shop for reference - but caution: some of these are written for the old version 4 (current is version 5)
