import ReferenceList from '@/components/references/reference-list';
import type { ReferenceListProps } from '@/components/references/reference-list';
export default function Page(props: ReferenceListProps) {
    return <ReferenceList kind="domains" {...props} />;
}
Page.layout = {
    breadcrumbs: [{ title: 'Référentiels', href: '/references/domains' }],
};
