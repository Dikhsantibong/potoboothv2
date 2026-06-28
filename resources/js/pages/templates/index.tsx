import { Head, useForm, Link, router } from '@inertiajs/react';
import { Plus, Edit, Trash2, MoreVertical, Search, Image as ImageIcon, Layout, Maximize, FileText, Layers, Grid, List } from 'lucide-react';
import { useState } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import templatesRoute from '@/routes/templates';
import { toast } from 'sonner';

interface Template {
    id: number;
    name: string;
    type: 'reguler' | 'koran' | 'flipbook';
    category: string | null;
    orientation: 'portrait' | 'landscape';
    template_path: string;
    image_width: number;
    image_height: number;
    frame_count: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface LinkProp {
    url: string | null;
    label: string;
    active: boolean;
}

interface TemplatePaginator {
    data: Template[];
    links: LinkProp[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Props {
    templates: TemplatePaginator;
    filters: {
        search?: string;
        type?: string;
        orientation?: string;
    };
}

export default function TemplateIndex({ templates, filters }: Props) {
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [orientationFilter, setOrientationFilter] = useState(filters.orientation || 'all');
    const [viewMode, setViewMode] = useState<'table' | 'gallery'>('gallery');

    const handleFilter = (key: string, value: string) => {
        const newFilters = {
            search: searchQuery,
            type: typeFilter,
            orientation: orientationFilter,
            [key]: value,
        };

        router.get(templatesRoute.index().url, newFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilter('search', searchQuery);
    };

    const handleToggleStatus = (template: Template) => {
        if (!template.is_active && template.frame_count === 0) {
            toast.error('Template harus memiliki minimal 1 frame untuk diaktifkan.');
            return;
        }
        router.patch(templatesRoute.toggle(template.id).url, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Status updated'),
        });
    };

    const openDeleteModal = (template: Template) => {
        setSelectedTemplate(template);
        setIsDeleteModalOpen(true);
    };

    const [isDeleting, setIsDeleting] = useState(false);
    const submitDelete = () => {
        if (!selectedTemplate) return;
        setIsDeleting(true);
        router.delete(templatesRoute.destroy(selectedTemplate.id).url, {
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                setSelectedTemplate(null);
                toast.success('Template deleted successfully');
            },
            onFinish: () => setIsDeleting(false),
        });
    };

    const getThumbnailUrl = (path: string) => {
        return path ? `/storage/${path}` : null;
    };

    return (
        <>
            <Head title="Manage Templates" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Templates</h2>
                        <p className="text-muted-foreground">
                            Manage your photobooth design templates here.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={templatesRoute.create().url}>
                            <Plus className="mr-2 h-4 w-4" /> Add Template
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="flex flex-col md:flex-row md:items-center gap-3 border-b p-3 justify-between">
                        <form onSubmit={handleSearch} className="relative flex-1 max-w-sm">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Search templates..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="bg-sidebar h-9 pl-9 w-full shadow-none focus-visible:ring-1"
                            />
                        </form>
                        <div className="flex flex-wrap items-center gap-2">
                            {(filters.search || filters.type || filters.orientation) && (
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        setSearchQuery('');
                                        setTypeFilter('all');
                                        setOrientationFilter('all');
                                        router.get(templatesRoute.index().url);
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
                                <SelectTrigger className="h-9 w-[130px] bg-sidebar shadow-none focus:ring-1">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="reguler">Reguler</SelectItem>
                                    <SelectItem value="koran">Koran</SelectItem>
                                    <SelectItem value="flipbook">Flipbook</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={orientationFilter} onValueChange={(val) => {
                                setOrientationFilter(val);
                                handleFilter('orientation', val);
                            }}>
                                <SelectTrigger className="h-9 w-[130px] bg-sidebar shadow-none focus:ring-1">
                                    <SelectValue placeholder="Orientation" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Orientation</SelectItem>
                                    <SelectItem value="portrait">Portrait</SelectItem>
                                    <SelectItem value="landscape">Landscape</SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="h-6 w-px bg-border mx-1 hidden sm:block"></div>
                            
                            <div className="flex items-center rounded-md border bg-muted/50 p-1">
                                <Button
                                    type="button"
                                    variant={viewMode === 'gallery' ? 'secondary' : 'ghost'}
                                    size="icon"
                                    className="h-7 w-7"
                                    onClick={() => setViewMode('gallery')}
                                >
                                    <Grid className="h-4 w-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                    size="icon"
                                    className="h-7 w-7"
                                    onClick={() => setViewMode('table')}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    {viewMode === 'table' ? (
                        <Table>
                        <TableHeader className='bg-sidebar'>
                            <TableRow>
                                <TableHead className="w-[80px]">Preview</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Orientation</TableHead>
                                <TableHead>Frames</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {templates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="h-24 text-center">
                                        {(filters.search || filters.type || filters.orientation)
                                            ? 'No templates match your filters.'
                                            : 'No templates found.'}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                templates.data.map((template) => (
                                    <TableRow key={template.id}>
                                        <TableCell>
                                            <div className="h-10 w-10 overflow-hidden rounded border bg-muted flex items-center justify-center">
                                                {template.template_path ? (
                                                    <img
                                                        src={getThumbnailUrl(template.template_path)!}
                                                        alt={template.name}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <ImageIcon className="h-5 w-5 text-muted-foreground/50" />
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            <div>
                                                {template.name}
                                                {template.category && (
                                                    <div className="text-xs text-muted-foreground">{template.category}</div>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="capitalize">
                                                {template.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1.5 text-sm">
                                                {template.orientation === 'portrait' ? (
                                                    <Layout className="h-3.5 w-3.5 rotate-90" />
                                                ) : (
                                                    <Layout className="h-3.5 w-3.5" />
                                                )}
                                                <span className="capitalize">{template.orientation}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1.5">
                                                <Layers className="h-3.5 w-3.5 text-muted-foreground" />
                                                <span>{template.frame_count}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                onClick={() => handleToggleStatus(template)}
                                                className="p-0 h-auto hover:bg-transparent"
                                            >
                                                <Badge variant={template.is_active ? 'default' : 'secondary'}>
                                                    {template.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </Button>
                                        </TableCell>
                                        <TableCell className='text-end'>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" className="h-8 w-8 p-0">
                                                        <MoreVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={templatesRoute.edit(template.id).url} className="flex items-center w-full">
                                                            <Edit className="mr-2 h-4 w-4" /> Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleToggleStatus(template)}
                                                    >
                                                        <Layers className="mr-2 h-4 w-4" />
                                                        {template.is_active ? 'Deactivate' : 'Activate'}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        className="text-destructive focus:text-destructive"
                                                        onClick={() => openDeleteModal(template)}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4 text-destructive" /> Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                    ) : (
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 p-4">
                            {templates.data.length === 0 ? (
                                <div className="col-span-full py-12 text-center text-muted-foreground">
                                    {(filters.search || filters.type || filters.orientation)
                                        ? 'No templates match your filters.'
                                        : 'No templates found.'}
                                </div>
                            ) : (
                                templates.data.map((template) => (
                                    <div key={template.id} className="group relative rounded-xl border bg-card overflow-hidden shadow-sm transition-all hover:shadow-md">
                                        <div className="aspect-[3/4] bg-muted relative overflow-hidden flex items-center justify-center">
                                            {template.template_path ? (
                                                <img
                                                    src={getThumbnailUrl(template.template_path)!}
                                                    alt={template.name}
                                                    className="h-full w-full object-cover transition-transform group-hover:scale-105"
                                                />
                                            ) : (
                                                <ImageIcon className="h-10 w-10 text-muted-foreground/30" />
                                            )}
                                            <div className="absolute top-2 right-2 flex flex-col gap-1 transition-opacity">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="secondary" size="icon" className="h-8 w-8 shadow-md bg-background/90 backdrop-blur-sm hover:bg-background">
                                                            <MoreVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={templatesRoute.edit(template.id).url} className="flex items-center w-full">
                                                                <Edit className="mr-2 h-4 w-4" /> Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() => handleToggleStatus(template)}
                                                        >
                                                            <Layers className="mr-2 h-4 w-4" />
                                                            {template.is_active ? 'Deactivate' : 'Activate'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            className="text-destructive focus:text-destructive"
                                                            onClick={() => openDeleteModal(template)}
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4 text-destructive" /> Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                            <div className="absolute top-2 left-2">
                                                <Badge variant={template.is_active ? 'default' : 'secondary'} className="shadow-sm">
                                                    {template.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="p-3">
                                            <h3 className="font-semibold truncate" title={template.name}>{template.name}</h3>
                                            {template.category && (
                                                <p className="text-xs text-muted-foreground truncate">{template.category}</p>
                                            )}
                                            <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                                <Badge variant="outline" className="capitalize text-[10px] px-1.5 py-0">
                                                    {template.type}
                                                </Badge>
                                                <div className="flex items-center gap-1.5">
                                                    {template.orientation === 'portrait' ? (
                                                        <Layout className="h-3 w-3 rotate-90" />
                                                    ) : (
                                                        <Layout className="h-3 w-3" />
                                                    )}
                                                    <span className="capitalize">{template.orientation}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                    <div className="border-t bg-sidebar/50">
                        <Pagination links={templates.links} className="py-3" />
                    </div>
                </div>
            </div>


            {/* Delete Confirmation Modal */}
            <Dialog open={isDeleteModalOpen} onOpenChange={setIsDeleteModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Template</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{selectedTemplate?.name}</strong>? This will also remove all associated frame configurations.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={submitDelete} disabled={isDeleting}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

TemplateIndex.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: templatesRoute.index().url,
        },
    ],
};
