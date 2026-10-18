<?php

namespace App\Enums;

/**
 * What a property or its condominium offers besides its rooms and areas. A fixed list, so the showcase
 * groups them the same way for every account and the portals can map them to their own codes.
 */
enum PropertyAmenity: string
{
    case AirConditioning = 'air_conditioning';
    case BuiltInWardrobes = 'built_in_wardrobes';
    case Furnished = 'furnished';
    case Balcony = 'balcony';
    case Backyard = 'backyard';
    case ServiceArea = 'service_area';
    case Barbecue = 'barbecue';
    case GourmetArea = 'gourmet_area';
    case Pool = 'pool';
    case Office = 'office';
    case Closet = 'closet';
    case SolarEnergy = 'solar_energy';
    case WaterTank = 'water_tank';
    case Well = 'well';
    case ElectricGate = 'electric_gate';
    case SecurityCameras = 'security_cameras';
    case PetsAllowed = 'pets_allowed';
    case GatedCommunity = 'gated_community';
    case Concierge = 'concierge';
    case Elevator = 'elevator';
    case CondoPool = 'condo_pool';
    case Gym = 'gym';
    case PartyRoom = 'party_room';
    case CondoGourmetArea = 'condo_gourmet_area';
    case Playground = 'playground';
    case SportsCourt = 'sports_court';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::AirConditioning => 'Ar-condicionado',
            self::BuiltInWardrobes => 'Armários planejados',
            self::Furnished => 'Mobiliado',
            self::Balcony => 'Varanda',
            self::Backyard => 'Quintal',
            self::ServiceArea => 'Área de serviço',
            self::Barbecue => 'Churrasqueira',
            self::GourmetArea => 'Área gourmet',
            self::Pool => 'Piscina',
            self::Office => 'Escritório',
            self::Closet => 'Closet',
            self::SolarEnergy => 'Energia solar',
            self::WaterTank => 'Cisterna',
            self::Well => 'Poço artesiano',
            self::ElectricGate => 'Portão eletrônico',
            self::SecurityCameras => 'Câmeras de segurança',
            self::PetsAllowed => 'Aceita animais',
            self::GatedCommunity => 'Condomínio fechado',
            self::Concierge => 'Portaria 24 horas',
            self::Elevator => 'Elevador',
            self::CondoPool => 'Piscina',
            self::Gym => 'Academia',
            self::PartyRoom => 'Salão de festas',
            self::CondoGourmetArea => 'Espaço gourmet',
            self::Playground => 'Playground',
            self::SportsCourt => 'Quadra de esportes',
        };
    }

    /**
     * Determine whether the condominium offers it, instead of the property itself.
     */
    public function isCondo(): bool
    {
        return in_array($this, [
            self::GatedCommunity,
            self::Concierge,
            self::Elevator,
            self::CondoPool,
            self::Gym,
            self::PartyRoom,
            self::CondoGourmetArea,
            self::Playground,
            self::SportsCourt,
        ], true);
    }
}
