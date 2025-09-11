# Data Flow

## General

### Sync

- cancel / reactivate -> built into payment plugin interface
  - cancel -> `AxytosPaymentMethod->cancelOrder` called
  - reactivate -> `AxytosPaymentMethod->reactivateOrder` called
- order state changes -> calls `Bootstrap->onUpdateStatus`
  - when state is `\BESTELLUNG_STATUS_VERSAND` -> Axytos state 'shipped' (call `AxytosPaymentMethod->orderWasShipped` )

## Invoices

- when order state is 'shipped' (see above) - order reported to Axytos as 'shipped'
- when invoice number for order received (different methods - see below) - order reported to Axytos as 'invoice'

### Receive invoice number

#### admin-menu tab 'invoices'

- shows list of orders that are shipped but not invoiced
- manual entry of invoice number for order

#### manual CSV upload

- upload button
- on upload, sends CSV to api endpoint

#### API endpoint

- can receive order-number -> invoice-number mapping
- accepts CSV or JSON
