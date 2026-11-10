import ErrorPage from './ErrorPage';

export default function TooManyRequests() {
    return (
        <ErrorPage
            status={429}
            title="Too many requests"
            description="You have made too many requests in a short time. Please wait a moment, then try again."
        />
    );
}
