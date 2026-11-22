@startuml
left to right direction
skinparam shadowing false
skinparam monochrome true
skinparam packageStyle rectangle

actor Tenant
actor Admin

rectangle "Garage Rental App" {
  usecase "Register" as Register
  usecase "Add vehicle" as AddVehicle
  usecase "Rent a garage" as Rent
  usecase "Find a free garage" as FindGarage
  usecase "Preview dates and price" as PreviewRent
  usecase "Upload payment proof" as UploadRentProof
  usecase "Add charging transaction" as Charge
  usecase "Preview kWh and cost" as PreviewCharge
  usecase "Upload charging proof" as UploadChargeProof
  usecase "Login" as Login
  usecase "Logout" as Logout
  usecase "View dashboard" as Dashboard
  usecase "View vehicles and detail" as ViewVehicles
  usecase "View rentals and detail" as ViewRentals
  usecase "View charging transactions" as ViewCharging
  usecase "View tenants" as ListTenants
  usecase "Approve or reject tenant" as Decide
  usecase "Create garages in a batch" as CreateGarages
  usecase "View garages and status" as ViewGarages
  usecase "View rentals running today" as ListRunning
  usecase "Extend a rental" as Extend
  usecase "Preview extra cost" as PreviewExtension
  usecase "Upload extension proof" as UploadExtensionProof
  usecase "View rental report" as Report
  usecase "Filter by dates, garage type, tenant" as Filter
}

Tenant --> Register
Tenant --> Login
Tenant --> Logout
Tenant --> Dashboard
Tenant --> AddVehicle
Tenant --> ViewVehicles
Tenant --> Rent
Tenant --> ViewRentals
Tenant --> Charge
Tenant --> ViewCharging
Admin --> Login
Admin --> Logout
Admin --> Dashboard
Admin --> ListTenants
Admin --> Decide
Admin --> CreateGarages
Admin --> ViewGarages
Admin --> ListRunning
Admin --> Extend
Admin --> ViewVehicles
Admin --> ViewRentals
Admin --> ViewCharging
Admin --> Report

Rent .> FindGarage : <<include>>
Rent .> PreviewRent : <<include>>
Rent .> UploadRentProof : <<include>>
Extend .> PreviewExtension : <<include>>
Extend .> UploadExtensionProof : <<include>>
Extend .> ListRunning : <<extend>>
Charge .> PreviewCharge : <<include>>
Charge .> UploadChargeProof : <<include>>
Report .> Filter : <<include>>
Decide .> ListTenants : <<extend>>
@enduml