export default function AppLogo() {
    return (
        <>
            <div className="bg-sidebar-black text-sidebar-primary-foreground flex aspect-square size-12 items-center justify-center rounded-md">
                <img src="kermen logo.png" alt="" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">Stock Management</span>
            </div>
        </>
    );
}
