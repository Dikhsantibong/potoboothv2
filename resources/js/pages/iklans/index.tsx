import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Edit, Trash2, MoreVertical, Search, Image as ImageIcon } from 'lucide-react';
import { useState, useRef } from 'react';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { toast } from 'sonner';

interface Iklan {
    id: number;
    title: string;
    image_path: string;
    image_url: string;
    type: string;
    status: string;
    link: string | null;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    updated_at: string;
}

interface LinkProp {
    url: string | null;
    label: string;
    active: boolean;
}

interface IklanPaginator {
    data: Iklan[];
    links: LinkProp[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Props {
    iklans: IklanPaginator;
    filters: {
        search?: string;
        type?: string;
        status?: string;
    };
}

export default function IklanIndex({ iklans, filters }: Props) {
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [selectedIklan, setSelectedIklan] = useState<Iklan | null>(null);
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    const handleFilter = (key: string, value: string) => {
        const newFilters = {
            search: searchQuery,
            type: typeFilter,
            status: statusFilter,
            [key]: value,
        };

        router.get('/iklans', newFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilter('search', searchQuery);
    };

    const fileInputRef = useRef<HTMLInputElement>(null);
    const editFileInputRef = useRef<HTMLInputElement>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        title: '',
        type: 'popup',
        status: 'active',
        image: null as File | null,
        link: '',
        description: '',
        start_date: '',
        end_date: '',
        _method: 'POST',
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setImagePreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const openCreateModal = () => {
        reset();
        clearErrors();
        setImagePreview(null);
        setIsCreateModalOpen(true);
    };

    const openEditModal = (iklan: Iklan) => {
        setSelectedIklan(iklan);
        setData({
            title: iklan.title,
            type: iklan.type,
            status: iklan.status,
            image: null,
            link: iklan.link || '',
            description: iklan.description || '',
            start_date: iklan.start_date ? iklan.start_date.split('T')[0] : '',
            end_date: iklan.end_date ? iklan.end_date.split('T')[0] : '',
            _method: 'PUT',
        });
        clearErrors();
        setImagePreview(iklan.image_url);
        setIsEditModalOpen(true);
    };

    const openDeleteModal = (iklan: Iklan) => {
        setSelectedIklan(iklan);
        setIsDeleteModalOpen(true);
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post('/iklans', {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                reset();
                toast.success('Iklan created successfully');
            },
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedIklan) return;
        post(`/iklans/${selectedIklan.id}`, {
            onSuccess: () => {
                setIsEditModalOpen(false);
                reset();
                toast.success('Iklan updated successfully');
            },
        });
    };

    const [isDeleting, setIsDeleting] = useState(false);
    const submitDelete = () => {
        if (!selectedIklan) return;
        setIsDeleting(true);
        router.delete(`/iklans/${selectedIklan.id}`, {
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                setSelectedIklan(null);
                toast.success('Iklan deleted successfully');
            },
            onFinish: () => setIsDeleting(false),
        });
    };

    return (
        <>
            <Head title="Manage Iklan / Banner" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Iklan & Banners</h2>
                        <p className="text-muted-foreground">
                            Kelola iklan popup dan banner untuk mesin potopi.
                        </p>
                    </div>
                    <Button onClick={openCreateModal}>
                        <Plus className="mr-2 h-4 w-4" /> Tambah Iklan
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="flex flex-col md:flex-row md:items-center gap-3 border-b p-3 justify-between">
                        <form onSubmit={handleSearch} className="relative flex-1 max-w-sm">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Cari iklan..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="bg-sidebar h-9 pl-9 w-full shadow-none focus-visible:ring-1"
                            />
                        </form>
                        <div className="flex flex-wrap items-center gap-2">
                            {(filters.search || filters.type || filters.status) && (
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        setSearchQuery('');
                                        setTypeFilter('all');
                                        setStatusFilter('all');
                                        router.get('/iklans');
                                    }}
                                    className="h-9 px-3 text-xs"
                                >
                                    Reset Filters
                                </Button>
                            )}
                            <Select value={typeFilter} onValueChange={(val) => {
                                setTypeFilter(val);
                                handleFilter('type', val);
                            }}>
                                <SelectTrigger className="h-9 w-[150px] bg-sidebar shadow-none focus:ring-1">
                                    <SelectValue placeholder="Semua Tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Tipe</SelectItem>
                                    <SelectItem value="popup">Popup</SelectItem>
                                    <SelectItem value="banner">Banner</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={statusFilter} onValueChange={(val) => {
                                setStatusFilter(val);
                                handleFilter('status', val);
                            }}>
                                <SelectTrigger className="h-9 w-[130px] bg-sidebar shadow-none focus:ring-1">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 divide-x divide-y border-collapse">
                        {iklans.data.length === 0 ? (
                            <div className="col-span-full py-8 text-center text-muted-foreground">
                                Tidak ada iklan ditemukan.
                            </div>
                        ) : (
                            iklans.data.map((iklan) => (
                                <div key={iklan.id} className="group relative aspect-[4/3] flex items-center justify-center p-4 hover:bg-muted/30 transition-colors border-r border-b">
                                    <img
                                        src={iklan.image_url}
                                        alt={iklan.title}
                                        className="max-h-full max-w-full object-contain transition-transform group-hover:scale-105 rounded"
                                    />
                                    
                                    <div className="absolute top-2 left-2 flex gap-1">
                                        <span className={`px-2 py-0.5 rounded text-[10px] font-medium text-white ${iklan.status === 'active' ? 'bg-green-500' : 'bg-gray-500'}`}>
                                            {iklan.status}
                                        </span>
                                        <span className="px-2 py-0.5 rounded text-[10px] font-medium text-white bg-blue-500 capitalize">
                                            {iklan.type}
                                        </span>
                                    </div>

                                    {/* Hover Actions */}
                                    <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="secondary" size="icon" className="h-8 w-8 shadow-md">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem onClick={() => openEditModal(iklan)}>
                                                    <Edit className="mr-2 h-4 w-4" /> Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onClick={() => openDeleteModal(iklan)}
                                                >
                                                    <Trash2 className="mr-2 h-4 w-4 text-destructive" /> Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>

                                    {/* Title Badge on Hover */}
                                    <div className="absolute bottom-2 left-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                        <div className="bg-black/80 text-white text-[11px] px-2 py-1.5 rounded text-center truncate">
                                            {iklan.title}
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                    <div className="border-t bg-sidebar/50">
                        <Pagination links={iklans.links} className="py-3" />
                    </div>
                </div>
            </div>

            {/* Create Iklan Modal */}
            <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                <DialogContent className="max-w-2xl">
                    <form onSubmit={submitCreate}>
                        <DialogHeader>
                            <DialogTitle>Tambah Iklan / Banner</DialogTitle>
                            <DialogDescription>
                                Upload gambar dan atur detail penayangan iklan.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid grid-cols-2 gap-4 py-4">
                            <div className="col-span-2 sm:col-span-1 flex flex-col gap-4">
                                <div
                                    className="h-48 w-full rounded-lg border-2 border-dashed flex items-center justify-center overflow-hidden cursor-pointer hover:border-primary transition-colors bg-muted/20"
                                    onClick={() => fileInputRef.current?.click()}
                                >
                                    {imagePreview ? (
                                        <img src={imagePreview} className="h-full w-full object-contain" />
                                    ) : (
                                        <div className="text-center p-4">
                                            <ImageIcon className="mx-auto h-8 w-8 text-muted-foreground mb-2" />
                                            <p className="text-sm font-medium">Klik untuk upload gambar</p>
                                            <p className="text-xs text-muted-foreground mt-1">Maks 5MB. Direkomendasikan rasio 4:3 untuk popup.</p>
                                        </div>
                                    )}
                                </div>
                                <input
                                    type="file"
                                    ref={fileInputRef}
                                    className="hidden"
                                    accept="image/*"
                                    onChange={handleFileChange}
                                />
                                {errors.image && <p className="text-xs text-destructive">{errors.image}</p>}
                                
                                <div className="grid gap-2 mt-2">
                                    <Label htmlFor="title">Judul Iklan</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="Promo Kemerdekaan..."
                                    />
                                    {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
                                </div>
                            </div>

                            <div className="col-span-2 sm:col-span-1 flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="type">Tipe Iklan</Label>
                                    <Select value={data.type} onValueChange={(val) => setData('type', val)}>
                                        <SelectTrigger id="type">
                                            <SelectValue placeholder="Pilih tipe" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="popup">Popup Layar Penuh</SelectItem>
                                            <SelectItem value="banner">Banner Berjalan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
                                </div>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="start_date">Mulai Tayang</Label>
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                        />
                                        {errors.start_date && <p className="text-xs text-destructive">{errors.start_date}</p>}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="end_date">Selesai Tayang</Label>
                                        <Input
                                            id="end_date"
                                            type="date"
                                            value={data.end_date}
                                            onChange={(e) => setData('end_date', e.target.value)}
                                        />
                                        {errors.end_date && <p className="text-xs text-destructive">{errors.end_date}</p>}
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-3 mt-auto">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="status">Status Aktif</Label>
                                        <p className="text-xs text-muted-foreground">Tampilkan di mesin potopi</p>
                                    </div>
                                    <Switch
                                        id="status"
                                        checked={data.status === 'active'}
                                        onCheckedChange={(checked) => setData('status', checked ? 'active' : 'inactive')}
                                    />
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCreateModalOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Simpan Iklan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Iklan Modal */}
            <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                <DialogContent className="max-w-2xl">
                    <form onSubmit={submitEdit}>
                        <DialogHeader>
                            <DialogTitle>Edit Iklan / Banner</DialogTitle>
                            <DialogDescription>
                                Ubah detail iklan atau ganti gambar.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid grid-cols-2 gap-4 py-4">
                            <div className="col-span-2 sm:col-span-1 flex flex-col gap-4">
                                <div
                                    className="h-48 w-full rounded-lg border-2 border-dashed flex items-center justify-center overflow-hidden cursor-pointer hover:border-primary transition-colors bg-muted/20"
                                    onClick={() => editFileInputRef.current?.click()}
                                >
                                    {imagePreview ? (
                                        <img src={imagePreview} className="h-full w-full object-contain" />
                                    ) : (
                                        <div className="text-center p-4">
                                            <ImageIcon className="mx-auto h-8 w-8 text-muted-foreground mb-2" />
                                            <p className="text-sm font-medium">Klik untuk upload gambar</p>
                                        </div>
                                    )}
                                </div>
                                <input
                                    type="file"
                                    ref={editFileInputRef}
                                    className="hidden"
                                    accept="image/*"
                                    onChange={handleFileChange}
                                />
                                <p className="text-[0.7rem] text-muted-foreground text-center">
                                    Biarkan kosong jika tidak ingin mengubah gambar.
                                </p>
                                {errors.image && <p className="text-xs text-destructive">{errors.image}</p>}
                                
                                <div className="grid gap-2 mt-2">
                                    <Label htmlFor="edit-title">Judul Iklan</Label>
                                    <Input
                                        id="edit-title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                    />
                                    {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
                                </div>
                            </div>

                            <div className="col-span-2 sm:col-span-1 flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-type">Tipe Iklan</Label>
                                    <Select value={data.type} onValueChange={(val) => setData('type', val)}>
                                        <SelectTrigger id="edit-type">
                                            <SelectValue placeholder="Pilih tipe" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="popup">Popup Layar Penuh</SelectItem>
                                            <SelectItem value="banner">Banner Berjalan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
                                </div>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-start_date">Mulai Tayang</Label>
                                        <Input
                                            id="edit-start_date"
                                            type="date"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                        />
                                        {errors.start_date && <p className="text-xs text-destructive">{errors.start_date}</p>}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-end_date">Selesai Tayang</Label>
                                        <Input
                                            id="edit-end_date"
                                            type="date"
                                            value={data.end_date}
                                            onChange={(e) => setData('end_date', e.target.value)}
                                        />
                                        {errors.end_date && <p className="text-xs text-destructive">{errors.end_date}</p>}
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-3 mt-auto">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="edit-status">Status Aktif</Label>
                                        <p className="text-xs text-muted-foreground">Tampilkan di mesin potopi</p>
                                    </div>
                                    <Switch
                                        id="edit-status"
                                        checked={data.status === 'active'}
                                        onCheckedChange={(checked) => setData('status', checked ? 'active' : 'inactive')}
                                    />
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsEditModalOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Simpan Perubahan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Modal */}
            <Dialog open={isDeleteModalOpen} onOpenChange={setIsDeleteModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Iklan</DialogTitle>
                        <DialogDescription>
                            Anda yakin ingin menghapus iklan <strong>{selectedIklan?.title}</strong>? Aksi ini tidak dapat dibatalkan dan akan langsung hilang dari seluruh mesin.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteModalOpen(false)}>
                            Batal
                        </Button>
                        <Button variant="destructive" onClick={submitDelete} disabled={isDeleting}>
                            {isDeleting ? 'Menghapus...' : 'Ya, Hapus'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

IklanIndex.layout = {
    breadcrumbs: [
        {
            title: 'Iklan & Banners',
            href: '/iklans',
        },
    ],
};
