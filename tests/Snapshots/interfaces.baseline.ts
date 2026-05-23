/**
 * Generated TypeScript interfaces
 *
 * This file is auto-generated. Do not edit directly.
 * Run `php artisan oi:gen-ts` to regenerate it.
 *
 * @generated <normalized>
*/

export interface IUser {
    id: number;
    name: string;
    email: string;
    age: number;
    bio: string;
    created_at: string;
    updated_at: string;
    full_name: string;
    posts?: IPost[];
    posts_count?: number;
    roles?: IRole[];
    roles_count?: number;
    memberships?: (IRole & { pivot?: IMembership })[];
    memberships_count?: number;
}

export interface IPost {
    id: number;
    title: string;
    content: string;
    published_at: string;
    user_id: string;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
    user?: IUser;
    comments?: IComment[];
    comments_count?: number;
    cover?: IAttachment;
}

export interface IComment {
    id: number;
    content: string;
    post_id: string;
    user_id: string;
    created_at: string;
    updated_at: string;
    post?: IPost;
    user?: IUser;
}

export interface IRole {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    updated_at: string;
    users?: IUser[];
    users_count?: number;
}

export interface IMembership {
    id: number;
    assigned_at: string;
    assigned_by: string;
    created_at: string;
    updated_at: string;
}

export interface IAttachment {
    id: number;
    filename: string;
    disk: string;
    role: string;
    attachable_id: string;
    attachable_type: string;
    created_at: string;
    updated_at: string;
    attachable?: never;
}

export interface IEvent {
    id: number;
    title: string;
    description: string;
    created_at: string;
}

