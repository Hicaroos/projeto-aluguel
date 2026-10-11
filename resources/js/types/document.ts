/** A file attached to a lease or a sale, served only to its account. */
export type AttachedDocument = {
    id: number;
    type: string;
    /** The file name as uploaded. */
    name: string;
    mime_type: string;
    /** In bytes. */
    size: number;
    created_at: string;
    url: string;
    download_url: string;
};
