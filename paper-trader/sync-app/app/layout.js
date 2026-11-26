export const metadata = {
  title: "Paper Desk Sync",
  description: "Private sync bridge for Paper Trader paper books",
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body
        style={{
          margin: 0,
          minHeight: "100vh",
          background: "#0f1419",
          color: "#e8eef5",
          fontFamily: '"Segoe UI", system-ui, sans-serif',
        }}
      >
        {children}
      </body>
    </html>
  );
}
