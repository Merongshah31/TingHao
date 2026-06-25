import './bootstrap';
import {
    Activity,
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowRight,
    ArrowUpRight,
    Bell,
    BellRing,
    Boxes,
    BrainCircuit,
    CalendarClock,
    ChartNoAxesCombined,
    ChevronDown,
    ChevronRight,
    CircleCheckBig,
    CircleHelp,
    createIcons,
    Database,
    DatabaseBackup,
    FileChartColumn,
    FolderKanban,
    History,
    LayoutDashboard,
    LogOut,
    Menu,
    MoreHorizontal,
    Package,
    Package2,
    PackagePlus,
    Plus,
    Search,
    Settings2,
    ShieldCheck,
    TrendingUp,
    TriangleAlert,
    Truck,
    Wheat,
} from 'lucide';

const dashboardIcons = {
    Activity,
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowRight,
    ArrowUpRight,
    Bell,
    BellRing,
    Boxes,
    BrainCircuit,
    CalendarClock,
    ChartNoAxesCombined,
    ChevronDown,
    ChevronRight,
    CircleCheckBig,
    CircleHelp,
    Database,
    DatabaseBackup,
    FileChartColumn,
    FolderKanban,
    History,
    LayoutDashboard,
    LogOut,
    Menu,
    MoreHorizontal,
    Package,
    Package2,
    PackagePlus,
    Plus,
    Search,
    Settings2,
    ShieldCheck,
    TrendingUp,
    TriangleAlert,
    Truck,
    Wheat,
};

const initializeDashboard = () => {
    createIcons({ icons: dashboardIcons });

    const shell = document.querySelector('[data-dashboard-shell]');
    const menuButton = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (! shell || ! menuButton || ! backdrop) {
        return;
    }

    const setSidebarOpen = (isOpen) => {
        shell.classList.toggle('sidebar-open', isOpen);
        menuButton.setAttribute('aria-expanded', String(isOpen));
        document.body.classList.toggle('dashboard-menu-open', isOpen);
    };

    menuButton.addEventListener('click', () => {
        setSidebarOpen(! shell.classList.contains('sidebar-open'));
    });

    backdrop.addEventListener('click', () => setSidebarOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });
};

document.addEventListener('DOMContentLoaded', initializeDashboard);
