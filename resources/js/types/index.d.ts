export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_admin?: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: { status?: string | null };
};

export interface Option {
    value: string;
    label: string;
}

export interface PersonRecord {
    folio: string;
    name: string;
    type: 'missing_person';
    type_label: string;
    status_label: string | null;
    sex: 'female' | 'male' | 'unknown' | null;
    sex_label: string | null;
    url: string;
    age: number | null;
    current_age: number | null;
    state: string | null;
    state_label: string | null;
    municipality: string | null;
    event_date: string | null;
    event_date_label: string | null;
    description: string | null;
    traits: { label: string; value: string }[];
    clothing: string | null;
    distinguishing_marks: string | null;
    authority: string | null;
    has_photo: boolean;
    published_at_label: string | null;
    updated_at_label: string | null;
    portrait: string;
    portrait_large: string;
}

export interface PersonRecordDetail extends PersonRecord {
    /** Solo llega a administradores. */
    registry_publish?: 'SI' | 'NO' | 'SIN DATO' | null;
    noticed_date_label: string | null;
    registered_date_label: string | null;
    source_updated_at_label: string | null;
    origin: string | null;
    search_only: boolean | null;
    referred_to: string[];
    migration_file: string | null;
    registered_age: {
        years: number | null;
        months: number | null;
        days: number | null;
    };
    nationality: string | null;
    speaks_spanish: boolean | null;
    has_disability: boolean | null;
    disability_type: string | null;
    sensitive_restricted: boolean;
    sensitive: {
        birth_date_label: string | null;
        birth_state: string | null;
        birth_place: string | null;
        street: string | null;
        exterior_number: string | null;
        interior_number: string | null;
        postal_code: string | null;
        neighborhood: string | null;
    } | null;
}

export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}

export interface RecordFilters {
    q: string | null;
    state: string | null;
    age: string | null;
    age_from: number | null;
    age_to: number | null;
    sex: string | null;
    status: string | null;
    photo: boolean | null;
    from: string | null;
    to: string | null;
    municipality: string | null;
    authority: string | null;
    nationality: string | null;
    disability: boolean | null;
    /** Solo llega a administradores. */
    registry: string | null;
    sort: string | null;
}

export interface RecordOptions {
    states: Option[];
    ageRanges: Option[];
    sexes: Option[];
    statuses: Option[];
    sorts: Option[];
    nationalities: Option[];
    /** Solo llega a administradores. */
    registry: Option[] | null;
}

export interface RequestFilters {
    q: string | null;
    state: string | null;
    age: string | null;
    type: string | null;
}

export interface RequestOptions {
    states: Option[];
    ageRanges: Option[];
    types: Option[];
}

export interface StatisticsGroup {
    value: string | null;
    label: string;
    count: number;
}

export interface StatisticsEntity {
    state: string;
    label: string;
    code: number;
    total: number;
    per_100k: number;
    registry_total: number;
    confidential: number;
    confidential_share: number;
    rank: number;
    rate_rank: number;
}

export interface StatisticsSummary {
    scope: string;
    total: number;
    share: number;
    rank: number | null;
    /** Lugar por registros cada 100 mil habitantes (solo al elegir un estado). */
    rate_rank: number | null;
    population: number;
    per_100k: number;
    registry_total: number;
    confidential: number;
    confidential_share: number;
    peak_year: { year: number; total: number } | null;
    /** Cifras de todo el país, para comparar un estado con ellas. */
    national: {
        total: number;
        population: number;
        per_100k: number;
        confidential_share: number;
    };
}

export interface StatisticsMunicipality {
    name: string;
    state: string | null;
    state_label: string | null;
    total: number;
    share: number;
    confidential: number;
    confidential_share: number;
}

export interface StatisticsFilters {
    state: string | null;
    from: number | null;
    to: number | null;
}

export interface RegistryStatistics {
    has_data: true;
    filters: StatisticsFilters;
    meta: {
        first_year: number | null;
        last_year: number | null;
        latest_month: string | null;
        imported_at: string | null;
    };
    totals: {
        registry: number;
        confidential: number;
        dated: number;
        undated: number;
    };
    summary: StatisticsSummary;
    entities: StatisticsEntity[];
    unknown_state: number;
    timeline: { month: string; total: number }[];
    profile: {
        known: number;
        sex: StatisticsGroup[];
        age: StatisticsGroup[];
        status: StatisticsGroup[];
    };
    municipalities: StatisticsMunicipality[];
    unknown_municipality: number;
}

export type StatisticsProps = RegistryStatistics | { has_data: false };

export interface PersonRequestItem {
    id: number;
    reference: string;
    type: 'search' | 'identification';
    type_label: string;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    name: string | null;
    sex: 'female' | 'male' | 'unknown' | null;
    sex_label: string | null;
    age: number | null;
    state: string | null;
    state_label: string | null;
    municipality: string | null;
    place: string | null;
    event_date_label: string | null;
    description: string;
    traits: { label: string; value: string }[];
    clothing: string | null;
    distinguishing_marks: string | null;
    institution: string | null;
    closed: boolean;
    closed_reason: 'resolved' | 'withdrawn' | null;
    closed_at_label: string | null;
    has_photo: boolean;
    photo: string | null;
    photo_thumb: string | null;
    url: string;
    created_at_label: string;
}
