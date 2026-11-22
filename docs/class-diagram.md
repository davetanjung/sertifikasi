classDiagram
    direction LR

    namespace Models {
        class User {
            +uuid id
            +string name
            +string email
            +string password
            +string role
            +string nik
            +string phone
            +string registration_status
            +datetime email_verified_at
            +isAdmin() bool
            +vehicles() HasMany
            +rentals() HasMany
            #casts() array
        }
        class Vehicle {
            +uuid id
            +uuid user_id
            +string type
            +string plate_number
            +string brand
            +string model
            +decimal maximum_capacity_kw
            +user() BelongsTo
            +rentals() HasMany
            #casts() array
        }
        class Garage {
            +uuid id
            +string code
            +string type
            +decimal daily_price
            +decimal price_per_kwh
            #int auto_number_length
            +autoNumberPrefix(data) string
            +rentals() HasMany
            +status() Attribute
            +isElectric() bool
            #casts() array
        }
        class Rental {
            +uuid id
            +uuid user_id
            +uuid garage_id
            +uuid vehicle_id
            +string code
            +string status
            +string rental_type
            +date start_date
            +date end_date
            +int days
            +decimal daily_price
            +decimal rent_amount
            +string payment_proof_path
            #int auto_number_length
            +autoNumberPrefix(data) string
            +user() BelongsTo
            +garage() BelongsTo
            +vehicle() BelongsTo
            +chargingTransactions() HasMany
            #casts() array
        }
        class ChargingTransaction {
            +uuid id
            +uuid rental_id
            +decimal current_capacity_kw
            +decimal charged_kwh
            +decimal price_per_kwh
            +decimal amount
            +string payment_proof_path
            +rental() BelongsTo
            #casts() array
        }
    }

    namespace ModelTraits {
        class HasCoreFeature {
            <<trait>>
            +getTableName()$ string
        }
        class HasAutoNumber {
            <<trait>>
            +bootHasAutoNumber()$ void
            +getAutoNumberColumn() string
            +autoNumberLength() int
            +autoNumberPrefix(data) string
            +getAutoNumber(data) string
            -buildNextAutoNumber(data) string
        }
        class HasKeywordSearch {
            <<trait>>
            +scopeKeywordSearch(query, keyword, columns)
        }
        class HasRoute {
            <<trait>>
            +routePrefix()$
            +routeIndex()$
            +routeCreate()$
            +routeShow(obj)$
            +routeEdit(obj)$
            +routeImport()$
            +permissionPrefix()$
            +permissionIndex()$ string
            +permissionCreate()$ string
            +permissionShow()$ string
            +permissionEdit()$ string
            +permissionDelete()$ string
            +permissionActivityLog()$ string
            +getRoutePrefix()
            +getRouteIndex() string
            +getRouteCreate() string
            +getRouteShow() string
            +getRouteEdit() string
            +getRouteImport() string
            +getPermissionPrefix()
            +getPermissionIndex() string
            +getPermissionCreate() string
            +getPermissionShow() string
            +getPermissionEdit() string
            +getPermissionDelete() string
            +getPermissionActivityLog() string
            +getFromCurrentRoute() string
            -routeAllowed(action) bool
            -validatePrefix() void
        }
        class HasCanAction {
            <<trait>>
            +canPermissionCreate()$ bool
            +canPermissionEdit()$ bool
            +canPermissionDelete()$ bool
            +canShow() bool
            +canCreate() bool
            +canEdit() bool
            +canDelete() bool
        }
    }

    namespace LivewireTraits {
        class HasCheckPermissionGate {
            <<trait>>
            +checkPermissionIndexGate() void
            +checkPermissionShowGate() void
            +checkPermissionCreateGate() void
            +checkPermissionEditGate() void
            +checkPermissionDeleteGate() void
            -authorizeAny(defaultPermissions) void
            -getModel()
        }
        class WithFormSubmit {
            <<trait>>
            +submitDefault() void
            +submitAndCreate() void
            +submitAndShow() void
            +submitAndBackToIndex() void
            +submit(validated) never
            #submitRedirectUrl() string
            -processSubmit(to)
        }
        class WithCreateForm {
            <<trait>>
            #layout string
            #submitMessage string
        }
        class WithEditForm {
            <<trait>>
            #layout string
            #submitMessage string
        }
        class WithIndexForm {
            <<trait>>
            +no_item int
            +perPage int
            +sortAsc bool
            +sortField string
            #layout string
            +sort(column, toggleSort) void
            +processFilter() void
            +delete(params) void
            +processDelete(id) void
            +processAction(action, parameters)
            -getQuery()*
            -guardDelete(id) void
        }
        class WithShowForm {
            <<trait>>
            #layout string
            +delete(params)
            +processDelete(id) void
            +processAction(action, parameters)
        }
    }

    namespace Services {
        class AuthService {
            <<final>>
            +login(email, password, remember)$ User
            +logout()$ void
            +home(user)$ string
        }
        class UserService {
            <<final>>
            +register(data)$ User
            +approve(user)$ User
            +reject(user)$ User
            +changeStatus(user, status)$ User
        }
        class VehicleService {
            <<final>>
            +create(data)$ Vehicle
        }
        class GarageService {
            <<final>>
            -int MAX_NUMBER$
            +createBatch(type, quantity, dailyPrice, pricePerKwh)$ Collection
            +nextCodes(type, quantity)$ array
            +typesFor(vehicleType)$ array
            +availableFor(type, start, end)$ Collection
            +availableForTypes(types, start, end, search, limit)$ array
            +whereFree(query, start, end)$ void
            -highestNumber(type)$ int
        }
        class RentalService {
            <<final>>
            -int MONTHLY_PRICE_FACTOR$
            +create(data)$ Rental
            +preview(data)$ array
            +activeRentals()$ Collection
            +period(rentalType, startDate, days)$ array
            +extend(rental, newEndDate, paymentProofPath)$ Rental
            +previewExtension(rental, newEndDate)$ array
            +latestEndDate(rental)$ CarbonInterface
            +isActive(rental)$ bool
            -calculateTerms(garage, data, vehicle)$ array
            -createRental(data, vehicle, garage)$ Rental
            -extendLocked(rental, newEndDate, paymentProofPath)$ Rental
            -extensionTerms(rental, newEndDate)$ array
            -rowsOf(rental)$ Collection
            -overlappingRentals(garageId, start, end, exceptCode)$ Collection
            -vehicleIsRented(vehicleId, start, end, exceptCode)$ bool
        }
        class ChargingTransactionService {
            <<final>>
            +create(rental, currentCapacityKw, paymentProofPath)$ ChargingTransaction
            +preview(rental, currentCapacityKw)$ array
            -calculate(rental, currentCapacityKw)$ array
        }
        class ReportService {
            <<final>>
            -int MINIMUM_YEAR$
            -int MAXIMUM_YEAR$
            +string BY_DAY$
            +string BY_MONTH$
            +forRange(startDate, endDate, garageType, userId)$ array
            -validateRange(startDate, endDate, garageType)$ array
            -parseDate(value, label)$ Carbon
            -rentalRows(start, end, garageType, userId)$ Collection
            -chargingRows(start, end, garageType, userId)$ Collection
            -rowsPerKey(rentals, chargings, keys, rentalKey, chargingKey, label)$ array
            -buildRow(rentals, chargings)$ array
        }
    }

    namespace LivewireAuth {
        class Login {
            <<final Livewire>>
            +email
            +password
            +bool remember
            #rules() array
            +process()
            +render() View
        }
        class Register {
            <<final Livewire>>
            +name
            +nik
            +phone
            +email
            +password
            +password_confirmation
            +bool registered
            #rules() array
            +submit()
            +render() View
        }
    }

    namespace LivewireUser {
        class UserIndex {
            <<final Livewire>>
            +keyword
            +status
            +mount() void
            +render() View
            -getQuery()
        }
        class UserShow {
            <<final Livewire>>
            +User obj
            +mount() void
            +approve() void
            +reject() void
            +render() View
            -changeRegistration(change, successMessage) void
        }
    }

    namespace LivewireVehicle {
        class VehicleIndex {
            <<final Livewire>>
            +keyword
            +type
            +mount() void
            +render() View
            -getQuery()
        }
        class VehicleCreate {
            <<final Livewire>>
            +type
            +plate_number
            +brand
            +vehicle_model
            +maximum_capacity_kw
            +mount() void
            #rules() array
            +submit(validated) Vehicle
            +render() View
            -typeOptions() array
        }
        class VehicleShow {
            <<final Livewire>>
            +Vehicle obj
            +mount() void
            +render() View
        }
    }

    namespace LivewireGarage {
        class GarageIndex {
            <<final Livewire>>
            +keyword
            +type
            +availability
            +mount() void
            +render() View
            -getQuery()
        }
        class GarageCreate {
            <<final Livewire>>
            +type
            +quantity
            +daily_price
            +price_per_kwh
            +boot() void
            +mount() void
            #rules() array
            +submit(validated) Garage
            #submitRedirectUrl() string
            +render() View
            -codeHint() array
        }
        class GarageShow {
            <<final Livewire>>
            +Garage obj
            +mount() void
            +render() View
        }
    }

    namespace LivewireRental {
        class RentalIndex {
            <<final Livewire>>
            +keyword
            +period
            +rental_type
            +mount() void
            +render() View
            -getQuery()
            -latestEndDateQuery() Builder
        }
        class RentalCreate {
            <<final Livewire>>
            +vehicle_id
            +rental_type
            +days
            +start_date
            +garage_id
            +payment_proof
            +garage_search
            +int garage_limit
            +mount() void
            #rules() array
            +updated(property) void
            +showMoreGarages() void
            +submit(validated) Rental
            +render() View
            -resolvePeriod() array
            -garageContext() array
            -garageList() array
            -selectedGarage(context) Garage
            -selectedGarageIsFree() bool
            -vehicleOptions() array
        }
        class RentalShow {
            <<final Livewire>>
            +Rental obj
            +mount() void
            +render() View
        }
    }

    namespace LivewireExtension {
        class ExtensionIndex {
            <<final Livewire>>
            +keyword
            +mount() void
            +render() View
            -rentals() Collection
            -matches(rental, keyword) bool
        }
        class ExtensionCreate {
            <<final Livewire>>
            +rental_id
            +new_end_date
            +payment_proof
            +mount() void
            #rules() array
            +submit(validated) Rental
            #submitRedirectUrl() string
            +render() View
            -loadRental() Rental
            -hasValidDate() bool
        }
    }

    namespace LivewireCharging {
        class ChargingIndex {
            <<final Livewire>>
            +keyword
            +mount() void
            +render() View
            -getQuery()
        }
        class ChargingCreate {
            <<final Livewire>>
            +rental_id
            +current_capacity_kw
            +payment_proof
            +mount() void
            #rules() array
            +submit(validated) ChargingTransaction
            #submitRedirectUrl() string
            +render() View
            -loadRental() Rental
            -maximumCapacity(rental) float
            -blockedReason(rental) string
        }
    }

    namespace LivewireReport {
        class ReportIndex {
            <<final Livewire>>
            +start_date
            +end_date
            +garage_type
            +user_id
            +mount() void
            +resetFilters() void
            +render() View
            -report() array
            -totals(rows) array
            -optionalString(value) string
        }
    }

    namespace View {
        class LoginView {
            <<blade auth.login>>
        }
        class RegisterView {
            <<blade auth.register>>
        }
        class DashboardView {
            <<blade dashboard>>
        }
        class UserIndexView {
            <<blade user.index>>
            data
            statusOptions
        }
        class UserShowView {
            <<blade user.show>>
            vehicles
            rentals
            canApprove
            canReject
        }
        class VehicleIndexView {
            <<blade vehicle.index>>
            data
            typeOptions
        }
        class VehicleCreateView {
            <<blade vehicle.create>>
            typeOptions
        }
        class VehicleShowView {
            <<blade vehicle.show>>
            rentals
        }
        class GarageIndexView {
            <<blade garage.index>>
            data
            typeOptions
            availabilityOptions
        }
        class GarageCreateView {
            <<blade garage.create>>
            typeOptions
            codeHint
        }
        class GarageShowView {
            <<blade garage.show>>
            status
            usedToday
            rentals
        }
        class RentalIndexView {
            <<blade rental.index>>
            data
            periodOptions
            rentalTypeOptions
        }
        class RentalCreateView {
            <<blade rental.create>>
            vehicles
            rentalTypeOptions
            garages
            garagesTotal
            garagesShown
            hasMoreGarages
            preview
            period
            previewError
        }
        class RentalShowView {
            <<blade rental.show>>
            extensions
            chargings
            latestEndDate
            totalRent
            totalCharging
            grandTotal
            isActive
            canCharge
            canExtend
        }
        class ExtensionIndexView {
            <<blade extension.index>>
            rentals
        }
        class ExtensionCreateView {
            <<blade extension.create>>
            rental
            latestEndDate
            preview
            previewError
            blockedReason
        }
        class ChargingIndexView {
            <<blade charging-transaction.index>>
            data
        }
        class ChargingCreateView {
            <<blade charging-transaction.create>>
            rental
            maximumCapacity
            preview
            previewError
            blockedReason
        }
        class ReportIndexView {
            <<blade report.index>>
            rows
            totals
            isDaily
            garageTypeOptions
            tenantOptions
            reportError
        }
    }

    User "1" --> "0..*" Vehicle : owns
    User "1" --> "0..*" Rental : rents
    Vehicle "1" --> "0..*" Rental : used in
    Garage "1" --> "0..*" Rental : booked in
    Rental "1" --> "0..*" ChargingTransaction : has
    User ..|> HasCoreFeature
    Vehicle ..|> HasCoreFeature
    Garage ..|> HasCoreFeature
    Rental ..|> HasCoreFeature
    ChargingTransaction ..|> HasCoreFeature
    Garage ..|> HasAutoNumber
    Rental ..|> HasAutoNumber
    HasCoreFeature ..> HasCanAction
    HasCoreFeature ..> HasKeywordSearch
    HasCoreFeature ..> HasRoute
    User <.. AuthService
    User <.. UserService
    Vehicle <.. VehicleService
    Garage <.. GarageService
    Rental <.. RentalService
    ChargingTransaction <.. ChargingTransactionService
    Rental <.. ReportService
    GarageService <.. RentalService
    RentalService <.. ChargingTransactionService
    WithCreateForm ..> HasCheckPermissionGate
    WithCreateForm ..> WithFormSubmit
    WithEditForm ..> HasCheckPermissionGate
    WithEditForm ..> WithFormSubmit
    WithIndexForm ..> HasCheckPermissionGate
    WithShowForm ..> HasCheckPermissionGate
    WithIndexForm <|.. UserIndex
    WithIndexForm <|.. VehicleIndex
    WithIndexForm <|.. GarageIndex
    WithIndexForm <|.. RentalIndex
    WithIndexForm <|.. ChargingIndex
    WithShowForm <|.. UserShow
    WithShowForm <|.. VehicleShow
    WithShowForm <|.. GarageShow
    WithShowForm <|.. RentalShow
    WithCreateForm <|.. VehicleCreate
    WithCreateForm <|.. GarageCreate
    WithCreateForm <|.. RentalCreate
    WithCreateForm <|.. ExtensionCreate
    WithCreateForm <|.. ChargingCreate
    AuthService <.. Login
    UserService <.. Register
    UserService <.. UserShow
    VehicleService <.. VehicleCreate
    GarageService <.. GarageCreate
    RentalService <.. RentalCreate
    RentalService <.. RentalShow
    RentalService <.. ExtensionIndex
    RentalService <.. ExtensionCreate
    ChargingTransactionService <.. ChargingCreate
    ReportService <.. ReportIndex
    Login --> LoginView
    Register --> RegisterView
    UserIndex --> UserIndexView
    UserShow --> UserShowView
    VehicleIndex --> VehicleIndexView
    VehicleCreate --> VehicleCreateView
    VehicleShow --> VehicleShowView
    GarageIndex --> GarageIndexView
    GarageCreate --> GarageCreateView
    GarageShow --> GarageShowView
    RentalIndex --> RentalIndexView
    RentalCreate --> RentalCreateView
    RentalShow --> RentalShowView
    ExtensionIndex --> ExtensionIndexView
    ExtensionCreate --> ExtensionCreateView
    ChargingIndex --> ChargingIndexView
    ChargingCreate --> ChargingCreateView
    ReportIndex --> ReportIndexView
    User <.. DashboardView
