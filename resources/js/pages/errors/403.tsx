import ErrorPage from './ErrorPage';

export default function Forbidden() {
    return (
        <ErrorPage
            status={403}
            title="Not allowed"
            description="You do not have permission to view this page. If you believe this is an error, contact your administrator."
        />
    );
}
