import { Head } from "@inertiajs/react";

export default function Compatibility({
    title,
    message,
}: {
    title: string;
    message: string;
}) {
    return (
        <main className="mx-auto max-w-2xl p-8">
            <Head title={title} />
            <h1 className="mb-4 text-2xl font-semibold">{title}</h1>
            <p role="alert">{message}</p>
            <p className="mt-4">
                Update the integration to support the React admin, then reload
                this page. No changes have been saved.
            </p>
        </main>
    );
}
