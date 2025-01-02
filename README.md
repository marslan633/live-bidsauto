# live-bidsauto
- Manufacture
- Model
- Generation
- Body Type
- Color
- Transmission
- Drive Wheel
- Fuel
- Condition
- Status
- VehicleType
- Domain
- Engine
- Seller
- SellerType
- Title
- DetailedTitle
- Damage
- Image
- Country 
- State
- City
- Location
- SellingBranch

Database Table fields
- id
- year
- title
- vin
- manufacturer_id -> foreign key
- vehicle_model_id -> foreign key
- generation_id -> foreign key
- body_type_id -> foreign key
- color_id -> foreign key
- engine_id -> foreign key
- transmission_id -> foreign key
- drive_wheel_id -> foreign key
- vehicle_type_id -> foreign key
- fuel_id -> foreign key
- cylinders
- salvage_id (lot->id)
- lot_id
- domain_id -> foreign key
- external_id
- odometer_km
- odometer_mi
- odometer_status
- estimate_repair_price
- pre_accident_price
- clean_wholesale_price
- actual_cash_value
- sale_date
- sale_date_updated_at
- bid
- bid_updated_at
- buy_now
- buy_now_updated_at
- final_bid
- final_bid_updated_at
- status -> foreign key
- seller_id -> foreign key
- seller_type_id -> foreign key
- title_id -> foreign key
- detailed_title_id -> foreign key
- damage_id -> foreign key
- damage_main -> foreign key
- damage_second -> foreign key
- keys_available
- airbags
- condition_id -> foreign key
- grade_iaai
- image_id -> foreign key
- country_id -> foreign key
- state_id -> foreign key
- city_id -> foreign key
- location_id -> foreign key
- selling_branch -> foreign key
- details


Base Table	Related Table	Pivot Table (Manufacture)
manufacturers	vehicle_models	manufacturer_vehicle_model
manufacturers	vehicle_types	manufacturer_vehicle_type
manufacturers	conditions	manufacturer_condition
manufacturers	fuels	manufacturer_fuel
manufacturers	seller_types	manufacturer_seller_type
manufacturers	drive_wheels	manufacturer_drive_wheel
manufacturers	transmissions	manufacturer_transmission
manufacturers	detailed_titles	manufacturer_detailed_title
manufacturers	damages	manufacturer_damage
manufacturers	domains	manufacturer_domain
manufacturers	years	manufacturer_year
manufacturers	buy_nows	manufacturer_buy_now


Base Table	Related Table	Pivot Table (VehicleModel)
vehicle_models	manufacturers	vehicle_model_manufacturer
vehicle_models	vehicle_types	vehicle_model_vehicle_type
vehicle_models	conditions	vehicle_model_condition
vehicle_models	fuels	vehicle_model_fuel
vehicle_models	seller_types	vehicle_model_seller_type
vehicle_models	drive_wheels	vehicle_model_drive_wheel
vehicle_models	transmissions	vehicle_model_transmission
vehicle_models	detailed_titles	vehicle_model_detailed_title
vehicle_models	damages	vehicle_model_damage
vehicle_models	domains	vehicle_model_domain
vehicle_models	years	vehicle_model_year
vehicle_models	buy_nows	vehicle_model_buy_now


Base Table	Related Table	Pivot Table (VehicleType)
vehicle_types	manufacturers	vehicle_type_manufacturer
vehicle_types	vehicle_models  vehicle_type_vehicle_model
vehicle_types	conditions	vehicle_type_condition
vehicle_types	fuels	vehicle_type_fuel
vehicle_types	seller_types	vehicle_type_seller_type
vehicle_types	drive_wheels	vehicle_type_drive_wheel
vehicle_types	transmissions	vehicle_type_transmission
vehicle_types	detailed_titles	vehicle_type_detailed_title
vehicle_types	damages	vehicle_type_damage
vehicle_types	domains	vehicle_type_domain
vehicle_types	years	vehicle_type_year
vehicle_types	buy_nows	vehicle_type_buy_now

Base Table	Related Table	Pivot Table (Condition)
conditions	manufacturers	condition_manufacturer
conditions	vehicle_models	condition_vehicle_model
conditions	vehicle_types	condition_vehicle_type
conditions	fuels	condition_fuel
conditions	seller_types	condition_seller_type
conditions	drive_wheels	condition_drive_wheel
conditions	transmissions	condition_transmission
conditions	detailed_titles	condition_detailed_title
conditions	damages	condition_damage
conditions	domains	condition_domain
conditions	years	condition_year
conditions	buy_nows	condition_buy_now

Base Table	Related Table	Pivot Table (Fuel)
fuels	manufacturers	fuel_manufacturer
fuels	vehicle_models	fuel_vehicle_model
fuels	vehicle_types	fuel_vehicle_type
fuels	conditions	fuel_condition
fuels	seller_types	fuel_seller_type
fuels	drive_wheels	fuel_drive_wheel
fuels	transmissions	fuel_transmission
fuels	detailed_titles	fuel_detailed_title
fuels	damages	fuel_damage
fuels	domains	fuel_domain
fuels	years	fuel_year
fuels	buy_nows	fuel_buy_now

Base Table	Related Table	Pivot Table (SellerType)
seller_types	manufacturers	seller_type_manufacturer
seller_types	vehicle_models	seller_type_vehicle_model
seller_types	vehicle_types	seller_type_vehicle_type
seller_types	conditions	seller_type_condition
seller_types	fuels	seller_type_fuel
seller_types	drive_wheels	seller_type_drive_wheel
seller_types	transmissions	seller_type_transmission
seller_types	detailed_titles	seller_type_detailed_title
seller_types	damages	seller_type_damage
seller_types	domains	seller_type_domain
seller_types	years	seller_type_year
seller_types	buy_nows	seller_type_buy_now

Base Table	Related Table	Pivot Table (DriveWheel)
drive_wheels	manufacturers	drive_wheel_manufacturer
drive_wheels	vehicle_models	drive_wheel_vehicle_model
drive_wheels	vehicle_types	drive_wheel_vehicle_type
drive_wheels	conditions	drive_wheel_condition
drive_wheels	fuels	drive_wheel_fuel
drive_wheels	seller_types	drive_wheel_seller_type
drive_wheels	transmissions	drive_wheel_transmission
drive_wheels	detailed_titles	drive_wheel_detailed_title
drive_wheels	damages	drive_wheel_damage
drive_wheels	domains	drive_wheel_domain
drive_wheels	years	drive_wheel_year
drive_wheels	buy_nows	drive_wheel_buy_now

Base Table	Related Table	Pivot Table (Transmission)
transmissions	manufacturers	transmission_manufacturer
transmissions	vehicle_models	transmission_vehicle_model
transmissions	vehicle_types	transmission_vehicle_type
transmissions	conditions	transmission_condition
transmissions	fuels	transmission_fuel
transmissions	seller_types	transmission_seller_type
transmissions	drive_wheels	transmission_drive_wheel
transmissions	detailed_titles	transmission_detailed_title
transmissions	damages	transmission_damage
transmissions	domains	transmission_domain
transmissions	years	transmission_year
transmissions	buy_nows	transmission_buy_now

Base Table	Related Table	Pivot Table (DetailedTitle)
detailed_titles	manufacturers	detailed_title_manufacturer
detailed_titles	vehicle_models	detailed_title_vehicle_model
detailed_titles	vehicle_types	detailed_title_vehicle_type
detailed_titles	conditions	detailed_title_condition
detailed_titles	fuels	detailed_title_fuel
detailed_titles	seller_types	detailed_title_seller_type
detailed_titles	drive_wheels	detailed_title_drive_wheel
detailed_titles	transmissions	detailed_title_transmission
detailed_titles	damages	detailed_title_damage
detailed_titles	domains	detailed_title_domain
detailed_titles	years	detailed_title_year
detailed_titles	buy_nows	detailed_title_buy_now

Base Table	Related Table	Pivot Table (Damage)
damages	manufacturers	damage_manufacturer
damages	vehicle_models	damage_vehicle_model
damages	vehicle_types	damage_vehicle_type
damages	conditions	damage_condition
damages	fuels	damage_fuel
damages	seller_types	damage_seller_type
damages	drive_wheels	damage_drive_wheel
damages	transmissions	damage_transmission
damages	detailed_titles	damage_detailed_title
damages	domains	damage_domain
damages	years	damage_year
damages	buy_nows	damage_buy_now

Base Table	Related Table	Pivot Table (Domain)
domains	manufacturers	domain_manufacturer
domains	vehicle_models	domain_vehicle_model
domains	vehicle_types	domain_vehicle_type
domains	conditions	domain_condition
domains	fuels	domain_fuel
domains	seller_types	domain_seller_type
domains	drive_wheels	domain_drive_wheel
domains	transmissions	domain_transmission
domains	detailed_titles	domain_detailed_title
domains	damages	domain_damage
domains	years	domain_year
domains	buy_nows	domain_buy_now

Base Table	Related Table	Pivot Table (Year)
years	manufacturers	year_manufacturer
years	vehicle_models	year_vehicle_model
years	vehicle_types	year_vehicle_type
years	conditions	year_condition
years	fuels	year_fuel
years	seller_types	year_seller_type
years	drive_wheels	year_drive_wheel
years	transmissions	year_transmission
years	detailed_titles	year_detailed_title
years	damages	year_damage
years	domains	year_domain
years	buy_nows	year_buy_now

Base Table	Related Table	Pivot Table (BuyNow)
buy_nows	manufacturers	buy_now_manufacturer
buy_nows	vehicle_models	buy_now_vehicle_model
buy_nows	vehicle_types	buy_now_vehicle_type
buy_nows	conditions	buy_now_condition
buy_nows	fuels	buy_now_fuel
buy_nows	seller_types	buy_now_seller_type
buy_nows	drive_wheels	buy_now_drive_wheel
buy_nows	transmissions	buy_now_transmission
buy_nows	detailed_titles	buy_now_detailed_title
buy_nows	damages	buy_now_damage
buy_nows	domains	buy_now_domain
buy_nows	years	buy_now_year