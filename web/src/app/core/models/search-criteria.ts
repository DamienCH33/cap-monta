import { AccommodationTypeKey, SortOrder } from './search-filters';

export interface SearchCriteria {
  arrival?: string;
  departure?: string;
  guests?: number;
  resort?: string;
  districts?: string[];
  types?: AccommodationTypeKey[];
  bedrooms?: number;
  amenities?: string[];
  order?: SortOrder;
  page?: number;
}
