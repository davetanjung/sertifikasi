flowchart LR
    Tenant([Tenant])
    Admin([Admin])

    subgraph System["Garage Rental App"]
        direction TB

        subgraph Access["Access"]
            UC_Register(["Register"])
            UC_Login(["Login"])
            UC_Logout(["Logout"])
            UC_Dashboard(["View dashboard"])
        end

        subgraph Tenants["Tenant management"]
            UC_ListTenants(["View tenants"])
            UC_Decide(["Approve or reject tenant"])
        end

        subgraph Vehicles["Vehicles"]
            UC_AddVehicle(["Add vehicle"])
            UC_ViewVehicles(["View vehicles and detail"])
        end

        subgraph Garages["Garages"]
            UC_CreateGarages(["Create garages in a batch"])
            UC_ViewGarages(["View garages and status"])
        end

        subgraph Rentals["Rentals"]
            UC_Rent(["Rent a garage"])
            UC_FindGarage(["Find a free garage"])
            UC_PreviewRent(["Preview dates and price"])
            UC_UploadRentProof(["Upload payment proof"])
            UC_ViewRentals(["View rentals and detail"])
        end

        subgraph Extensions["Extensions"]
            UC_ListRunning(["View rentals running today"])
            UC_Extend(["Extend a rental"])
            UC_PreviewExtension(["Preview extra cost"])
            UC_UploadExtensionProof(["Upload extension proof"])
        end

        subgraph Charging["Electric car charging"]
            UC_Charge(["Add charging transaction"])
            UC_PreviewCharge(["Preview kWh and cost"])
            UC_UploadChargeProof(["Upload charging proof"])
            UC_ViewCharging(["View charging transactions"])
        end

        subgraph Reports["Reports"]
            UC_Report(["View rental report"])
            UC_Filter(["Filter by dates, garage type, tenant"])
        end
    end

    Tenant --- UC_Register
    Tenant --- UC_Login
    Tenant --- UC_Logout
    Tenant --- UC_Dashboard
    Tenant --- UC_AddVehicle
    Tenant --- UC_ViewVehicles
    Tenant --- UC_Rent
    Tenant --- UC_ViewRentals
    Tenant --- UC_Charge
    Tenant --- UC_ViewCharging

    Admin --- UC_Login
    Admin --- UC_Logout
    Admin --- UC_Dashboard
    Admin --- UC_ListTenants
    Admin --- UC_Decide
    Admin --- UC_CreateGarages
    Admin --- UC_ViewGarages
    Admin --- UC_ListRunning
    Admin --- UC_Extend
    Admin --- UC_ViewVehicles
    Admin --- UC_ViewRentals
    Admin --- UC_ViewCharging
    Admin --- UC_Report

    UC_Rent -.->|include| UC_FindGarage
    UC_Rent -.->|include| UC_PreviewRent
    UC_Rent -.->|include| UC_UploadRentProof
    UC_Extend -.->|include| UC_PreviewExtension
    UC_Extend -.->|include| UC_UploadExtensionProof
    UC_Extend -.->|extends from| UC_ListRunning
    UC_Charge -.->|include| UC_PreviewCharge
    UC_Charge -.->|include| UC_UploadChargeProof
    UC_Report -.->|include| UC_Filter
    UC_Decide -.->|extends from| UC_ListTenants
