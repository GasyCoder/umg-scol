import ReferenceForm from '@/components/references/reference-form';
import type { ReferenceFormProps } from '@/components/references/reference-form';
export default function Page(props: ReferenceFormProps) {
    return <ReferenceForm kind="levels" {...props} />;
}
Page.layout = {
    breadcrumbs: [{ title: 'Référentiels', href: '/references/levels' }],
};
