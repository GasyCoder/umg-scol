import ReferenceForm from '@/components/references/reference-form';
import type { ReferenceFormProps } from '@/components/references/reference-form';
export default function Page(props: ReferenceFormProps) {
    return <ReferenceForm kind="academic-years" {...props} />;
}
Page.layout = {
    breadcrumbs: [
        { title: 'Référentiels', href: '/references/academic-years' },
    ],
};
