import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

type Origin = 'LOCAL' | 'IMPORTED';

interface ProductOption {
    id: string;
    name: string;
    base_unit: string | null;
}

interface ManagedVariant {
    id: string;
    product_id: string;
    product_name: string | null;
    base_unit: string | null;
    origin: Origin | null;
    color: string | null;
    sku: string | null;
    thickness: string | null;
    size: string | null;
    created_at: string;
}

interface ProductsPageProps {
    variants: {
        data: ManagedVariant[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search: string;
        origin: '' | Origin;
        color: string;
    };
    productOptions: ProductOption[];
    origins: Origin[];
    colors: string[];
}

interface ProductFormData {
    name: string;
    base_unit: string;
}

interface VariantFormData {
    product_id: string;
    color: string;
    origin: '' | Origin;
    sku: string;
    thickness: string;
    size: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Product Management',
        href: '/products',
    },
];

function formatValue(value: string | null): string {
    return value && value.trim() !== '' ? value : '-';
}

function ValueBadges({ values }: { values: string[] }) {
    if (values.length === 0) {
        return <span className="text-muted-foreground">-</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {values.map((value) => (
                <Badge key={value} variant="secondary" className="rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase">
                    {value}
                </Badge>
            ))}
        </div>
    );
}

export default function ProductManagementPage({ variants, filters, productOptions, origins, colors }: ProductsPageProps) {
    const { auth } = usePage<{ auth: { user: { role: 'ADMIN' | 'SALES' | 'AUDITOR' } } }>().props;
    const canManageProducts = auth.user.role === 'ADMIN';

    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
    const [originFilter, setOriginFilter] = useState<Origin | ''>(filters.origin ?? '');
    const [colorFilter, setColorFilter] = useState(filters.color ?? '');
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isVariantDialogOpen, setIsVariantDialogOpen] = useState(false);

    const createForm = useForm<ProductFormData>({
        name: '',
        base_unit: '',
    });

    const variantForm = useForm<VariantFormData>({
        product_id: '',
        color: '',
        origin: '',
        sku: '',
        thickness: '',
        size: '',
    });

    const searchVariants: FormEventHandler = (event) => {
        event.preventDefault();

        router.get(
            route('products.index'),
            {
                search: searchTerm || undefined,
                origin: originFilter || undefined,
                color: colorFilter || undefined,
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
        setOriginFilter('');
        setColorFilter('');

        router.get(route('products.index'), {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const goToPage = (page: number) => {
        if (page < 1 || page > variants.last_page) {
            return;
        }

        router.get(
            route('products.index'),
            {
                page,
                search: filters.search || undefined,
                origin: filters.origin || undefined,
                color: filters.color || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const submitCreate: FormEventHandler = (event) => {
        event.preventDefault();

        createForm.post(route('products.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setIsCreateDialogOpen(false);
            },
        });
    };

    const submitVariant: FormEventHandler = (event) => {
        event.preventDefault();

        variantForm.post(route('products.variants.store'), {
            preserveScroll: true,
            onSuccess: () => {
                variantForm.reset();
                variantForm.clearErrors();
                setIsVariantDialogOpen(false);
            },
        });
    };

    const resultStart = variants.total === 0 ? 0 : (variants.current_page - 1) * variants.per_page + 1;
    const resultEnd = Math.min(variants.current_page * variants.per_page, variants.total);

    const originOptions = useMemo(() => [...origins], [origins]);
    const colorOptions = useMemo(() => [...colors], [colors]);
    const productOptionsMemo = useMemo(() => [...productOptions], [productOptions]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Product Management" />

            <div className="space-y-6 p-4">
                <div className="rounded-xl border p-4 md:p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-semibold">Product Management</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Manage products, create variants, and review every variant as its own table row.
                            </p>
                        </div>

                        {canManageProducts ? (
                            <div className="flex flex-wrap gap-2">
                                <Button onClick={() => setIsCreateDialogOpen(true)}>Add Product</Button>
                                <Button variant="secondary" onClick={() => setIsVariantDialogOpen(true)}>
                                    Add Product Variant
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={route('products.import')}>Import JSON</Link>
                                </Button>
                            </div>
                        ) : null}
                    </div>

                    <form onSubmit={searchVariants} className="mt-6 grid gap-3 md:grid-cols-[1fr_180px_180px_auto_auto]">
                        <div className="grid gap-2">
                            <Label htmlFor="search">Search by product or SKU</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    id="search"
                                    value={searchTerm}
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                    className="pl-9"
                                    placeholder="Search variants"
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="origin">Origin</Label>
                            <Select
                                value={originFilter || '__all'}
                                onValueChange={(value) => setOriginFilter(value === '__all' ? '' : (value as Origin))}
                            >
                                <SelectTrigger id="origin">
                                    <SelectValue placeholder="All origins" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all">All origins</SelectItem>
                                    {originOptions.map((origin) => (
                                        <SelectItem key={origin} value={origin}>
                                            {origin}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="color">Color</Label>
                            <Select value={colorFilter || '__all'} onValueChange={(value) => setColorFilter(value === '__all' ? '' : value)}>
                                <SelectTrigger id="color">
                                    <SelectValue placeholder="All colors" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all">All colors</SelectItem>
                                    {colorOptions.map((color) => (
                                        <SelectItem key={color} value={color}>
                                            {color}
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
                                    <th className="px-4 py-3 text-left font-medium">Product</th>
                                    <th className="px-4 py-3 text-left font-medium">Base Unit</th>
                                    <th className="px-4 py-3 text-left font-medium">Origin</th>
                                    <th className="px-4 py-3 text-left font-medium">Color</th>
                                    <th className="px-4 py-3 text-left font-medium">SKU</th>
                                    <th className="px-4 py-3 text-left font-medium">Thickness</th>
                                    <th className="px-4 py-3 text-left font-medium">Size</th>
                                    <th className="px-4 py-3 text-left font-medium">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                {variants.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="text-muted-foreground px-4 py-8 text-center">
                                            No product variants found.
                                        </td>
                                    </tr>
                                ) : (
                                    variants.data.map((variant) => (
                                        <tr key={variant.id} className="border-t align-top">
                                            <td className="px-4 py-3 font-medium">
                                                <div className="space-y-1">
                                                    <div>{variant.product_name ?? '-'}</div>
                                                    <div className="text-muted-foreground text-xs">Variant ID: {variant.id}</div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">{formatValue(variant.base_unit)}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline" className="rounded-md px-2.5 py-0.5 text-[11px]">
                                                    {variant.origin ?? '-'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">{formatValue(variant.color)}</td>
                                            <td className="px-4 py-3">{formatValue(variant.sku)}</td>
                                            <td className="px-4 py-3">{formatValue(variant.thickness)}</td>
                                            <td className="px-4 py-3">{formatValue(variant.size)}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {new Date(variant.created_at).toLocaleDateString(undefined, {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
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
                        Showing {resultStart}-{resultEnd} of {variants.total}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToPage(variants.current_page - 1)}
                            disabled={variants.current_page <= 1}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToPage(variants.current_page + 1)}
                            disabled={variants.current_page >= variants.last_page}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            {canManageProducts ? (
                <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Add New Product</DialogTitle>
                            <DialogDescription>Create a new product record.</DialogDescription>
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
                                <Label htmlFor="create-base-unit">Base Unit</Label>
                                <Input
                                    id="create-base-unit"
                                    value={createForm.data.base_unit}
                                    onChange={(event) => createForm.setData('base_unit', event.target.value)}
                                    placeholder="Piece, meter, box..."
                                />
                                <InputError message={createForm.errors.base_unit} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={createForm.processing}>
                                    {createForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Save Product
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            ) : null}

            {canManageProducts ? (
                <Dialog open={isVariantDialogOpen} onOpenChange={setIsVariantDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Add Product Variant</DialogTitle>
                            <DialogDescription>Select a product and define the variant details.</DialogDescription>
                        </DialogHeader>

                        <form className="space-y-4" onSubmit={submitVariant}>
                            <div className="grid gap-2">
                                <Label htmlFor="variant-product">Product</Label>
                                <Select
                                    value={variantForm.data.product_id || '__none'}
                                    onValueChange={(value) => variantForm.setData('product_id', value === '__none' ? '' : value)}
                                >
                                    <SelectTrigger id="variant-product">
                                        <SelectValue placeholder="Select product" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">Select product</SelectItem>
                                        {productOptionsMemo.map((product) => (
                                            <SelectItem key={product.id} value={product.id}>
                                                {product.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={variantForm.errors.product_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="variant-origin">Origin</Label>
                                <Select
                                    value={variantForm.data.origin || '__none'}
                                    onValueChange={(value) => variantForm.setData('origin', value === '__none' ? '' : (value as Origin))}
                                >
                                    <SelectTrigger id="variant-origin">
                                        <SelectValue placeholder="Select origin" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">None</SelectItem>
                                        <SelectItem value="LOCAL">LOCAL</SelectItem>
                                        <SelectItem value="IMPORTED">IMPORTED</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={variantForm.errors.origin} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="variant-color">Color</Label>
                                <Input
                                    id="variant-color"
                                    value={variantForm.data.color}
                                    onChange={(event) => variantForm.setData('color', event.target.value)}
                                    placeholder="Blue"
                                />
                                <InputError message={variantForm.errors.color} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="variant-sku">SKU</Label>
                                <Input
                                    id="variant-sku"
                                    value={variantForm.data.sku}
                                    onChange={(event) => variantForm.setData('sku', event.target.value)}
                                    placeholder="SKU-001"
                                />
                                <InputError message={variantForm.errors.sku} />
                            </div>

                            <div className="grid gap-2 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="variant-thickness">Thickness</Label>
                                    <Input
                                        id="variant-thickness"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={variantForm.data.thickness}
                                        onChange={(event) => variantForm.setData('thickness', event.target.value)}
                                        placeholder="1.25"
                                    />
                                    <InputError message={variantForm.errors.thickness} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="variant-size">Size</Label>
                                    <Input
                                        id="variant-size"
                                        value={variantForm.data.size}
                                        onChange={(event) => variantForm.setData('size', event.target.value)}
                                        placeholder="XL"
                                    />
                                    <InputError message={variantForm.errors.size} />
                                </div>
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={variantForm.processing}>
                                    {variantForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Save Variant
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            ) : null}
        </AppLayout>
    );
}
