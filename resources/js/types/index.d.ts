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
}

export interface RecordOptions {
    states: Option[];
    ageRanges: Option[];
}

export interface RequestFilters extends RecordFilters {
    type: string | null;
}

export interface RequestOptions extends RecordOptions {
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
