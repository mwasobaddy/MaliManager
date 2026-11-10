import ErrorPage from './ErrorPage';

export default function NotFound() {
    return (
        <ErrorPage
            status={404}
            title="Page not found"
            description="The page you are looking for does not exist or was moved. Check the address, or head back home."
        />
    );
}
