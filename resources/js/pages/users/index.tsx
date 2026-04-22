import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Role = 'ADMIN' | 'SALES' | 'AUDITOR';

interface ManagedUser {
    id: string;
    name: string;
    email: string;
    phone: string;
    role: Role;
}

interface UsersPageProps {
    users: {
        data: ManagedUser[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search: string;
        role: '' | Role;
    };
    roles: Role[];
    defaultPassword: string;
}

interface UserFormData {
    name: string;
    email: string;
    phone: string;
    role: Role;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: '/users',
    },
];

const defaultRole: Role = 'SALES';

export default function UserManagementPage({ users, filters, roles, defaultPassword }: UsersPageProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
    const [roleFilter, setRoleFilter] = useState<Role | ''>(filters.role ?? '');
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [deletingUser, setDeletingUser] = useState<ManagedUser | null>(null);

    const createForm = useForm<UserFormData>({
        name: '',
        email: '',
        phone: '',
        role: defaultRole,
    });

    const editForm = useForm<UserFormData>({
        name: '',
        email: '',
        phone: '',
        role: defaultRole,
    });

    const searchUsers: FormEventHandler = (event) => {
        event.preventDefault();

        router.get(
            route('users.index'),
            {
                search: searchTerm || undefined,
                role: roleFilter || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const clearFilters = () => {
        setSearchTerm('');
        setRoleFilter('');

        router.get(route('users.index'), {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const goToPage = (page: number) => {
        if (page < 1 || page > users.last_page) {
            return;
        }

        router.get(
            route('users.index'),
            {
                page,
                search: filters.search || undefined,
                role: filters.role || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const submitCreate: FormEventHandler = (event) => {
        event.preventDefault();

        createForm.post(route('users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createForm.setData('role', defaultRole);
                setIsCreateDialogOpen(false);
            },
        });
    };

    const openEditDialog = (user: ManagedUser) => {
        setEditingUser(user);
        editForm.clearErrors();
        editForm.setData({
            name: user.name,
            email: user.email,
            phone: user.phone,
            role: user.role,
        });
    };

    const submitEdit: FormEventHandler = (event) => {
        event.preventDefault();

        if (!editingUser) {
            return;
        }

        editForm.put(route('users.update', editingUser.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingUser(null);
                editForm.clearErrors();
            },
        });
    };

    const confirmDelete = () => {
        if (!deletingUser) {
            return;
        }

        router.delete(route('users.destroy', deletingUser.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingUser(null),
        });
    };

    const resultStart = users.total === 0 ? 0 : (users.current_page - 1) * users.per_page + 1;
    const resultEnd = Math.min(users.current_page * users.per_page, users.total);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">User Management</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Manage users and roles. New users receive the default password: {defaultPassword}
                            </p>
                        </div>

                        <Button onClick={() => setIsCreateDialogOpen(true)}>Add User</Button>
                    </div>

                    <form onSubmit={searchUsers} className="mt-6 grid gap-3 md:grid-cols-[1fr_220px_auto_auto]">
                        <div className="grid gap-2">
                            <Label htmlFor="search">Search</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    id="search"
                                    value={searchTerm}
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                    className="pl-9"
                                    placeholder="Search by name, email, or phone"
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Filter by role</Label>
                            <Select value={roleFilter || '__all'} onValueChange={(value) => setRoleFilter(value === '__all' ? '' : (value as Role))}>
                                <SelectTrigger id="role">
                                    <SelectValue placeholder="All roles" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all">All roles</SelectItem>
                                    {roles.map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button type="submit" className="self-end">
                            Apply
                        </Button>
                        <Button type="button" variant="outline" className="self-end" onClick={clearFilters}>
                            Reset
                        </Button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Name</th>
                                    <th className="px-4 py-3 text-left font-medium">Email</th>
                                    <th className="px-4 py-3 text-left font-medium">Phone</th>
                                    <th className="px-4 py-3 text-left font-medium">Role</th>
                                    <th className="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                            No users found.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="border-t">
                                            <td className="px-4 py-3">{user.name}</td>
                                            <td className="px-4 py-3">{user.email}</td>
                                            <td className="px-4 py-3">{user.phone}</td>
                                            <td className="px-4 py-3 capitalize">{user.role}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button type="button" variant="outline" size="sm" onClick={() => openEditDialog(user)}>
                                                        Edit
                                                    </Button>
                                                    <Button type="button" variant="destructive" size="sm" onClick={() => setDeletingUser(user)}>
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <p className="text-muted-foreground text-sm">
                        Showing {resultStart}-{resultEnd} of {users.total}
                    </p>

                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={() => goToPage(users.current_page - 1)} disabled={users.current_page <= 1}>
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToPage(users.current_page + 1)}
                            disabled={users.current_page >= users.last_page}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add New User</DialogTitle>
                        <DialogDescription>Create a user account. Password is set to the system default automatically.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitCreate}>
                        <div className="grid gap-2">
                            <Label htmlFor="create-name">Name</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(event) => createForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="create-email">Email</Label>
                            <Input
                                id="create-email"
                                type="email"
                                value={createForm.data.email}
                                onChange={(event) => createForm.setData('email', event.target.value)}
                                required
                            />
                            <InputError message={createForm.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="create-phone">Phone</Label>
                            <Input
                                id="create-phone"
                                value={createForm.data.phone}
                                onChange={(event) => createForm.setData('phone', event.target.value)}
                                required
                            />
                            <InputError message={createForm.errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="create-role">Role</Label>
                            <Select value={createForm.data.role} onValueChange={(value) => createForm.setData('role', value as Role)}>
                                <SelectTrigger id="create-role">
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={createForm.errors.role} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!editingUser} onOpenChange={(open) => !open && setEditingUser(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit User</DialogTitle>
                        <DialogDescription>Update user profile and role details.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitEdit}>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(event) => editForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={editForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={editForm.data.email}
                                onChange={(event) => editForm.setData('email', event.target.value)}
                                required
                            />
                            <InputError message={editForm.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-phone">Phone</Label>
                            <Input
                                id="edit-phone"
                                value={editForm.data.phone}
                                onChange={(event) => editForm.setData('phone', event.target.value)}
                                required
                            />
                            <InputError message={editForm.errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-role">Role</Label>
                            <Select value={editForm.data.role} onValueChange={(value) => editForm.setData('role', value as Role)}>
                                <SelectTrigger id="edit-role">
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={editForm.errors.role} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Update User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!deletingUser} onOpenChange={(open) => !open && setDeletingUser(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete User</DialogTitle>
                        <DialogDescription>Are you sure you want to delete {deletingUser?.name}? This action cannot be undone.</DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="button" variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
