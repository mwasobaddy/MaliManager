import ErrorPage from './ErrorPage';

export default function ServerError() {
    return (
        <ErrorPage
            status={500}
            title="Something went wrong"
            description="An unexpected error occurred. Please try again — if it keeps happening, contact support."
        />
    );
}
