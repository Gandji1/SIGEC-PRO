// Composants UI réutilisables
export { default as ConfirmModal } from './ConfirmModal';
export { default as EmptyState } from './EmptyState';
export { default as Pagination } from './Pagination';
export { default as StatCard } from './StatCard';
export { default as StatusBadge } from './StatusBadge';
export { default as SearchInput } from './SearchInput';
export { default as ExportButton } from './ExportButton';
export { default as PaymentModal } from './PaymentModal';

// Composants Skeleton
export { 
  Skeleton,
  TextSkeleton,
  StatCardSkeleton,
  TableSkeleton,
  ProductGridSkeleton,
  OrderCardSkeleton,
  FormSkeleton,
  DashboardSkeleton,
  ListPageSkeleton,
  POSSkeleton 
} from './Skeleton';

// Composants Toast
export { ToastProvider, useToast } from './Toast';

// Composants de layout
export { default as Layout } from './Layout';
export { default as GlobalSearch } from './GlobalSearch';

// Composants de protection
export { default as ProtectedRoute } from './ProtectedRoute';
export { default as RoleGate } from './RoleGate';
export { default as SubscriptionGuard } from './SubscriptionGuard';

// Alertes
export { default as LowStockAlert } from './LowStockAlert';
export { default as SubscriptionAlert } from './SubscriptionAlert';
