export interface CrossReferenceSelection {
    target_type: 'article' | 'topic';
    target_id: number;
    target_encyclopedia_id: number;
    title: string;
    encyclopedia_title?: string | null;
    encyclopedia_slug?: string | null;
    excerpt?: string | null;
    note?: string | null;
    available?: boolean;
    key?: string;
}
