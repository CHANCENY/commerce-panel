<?php

namespace Simp\Commerce\commerce_panel;

use PDO;
use Simp\Commerce\order\Order;

class CommerceSummary
{
    protected PDO $connection;
    public function __construct()
    {
        $this->connection = DB_CONNECTION->connect();
    }

    public function orders(): array
    {
        $query = "SELECT id FROM commerce_order";
        $stmt = $this->connection->prepare($query);
        $stmt->execute();

        return array_map(function ($order) {
            return new Order($order['id']);
        },  $stmt->fetchAll(PDO::FETCH_ASSOC));

    }

    public function getThisMonthSummary(int $year, int $month): array
    {
        $query = "
        SELECT 
            currency,
            status,
            COUNT(*) AS total_orders,
            SUM(subtotal) AS total_subtotal,
            SUM(tax_total) AS total_tax,
            SUM(discount_total) AS total_discount,
            SUM(shipping_total) AS total_shipping,
            SUM(grand_total) AS total_grand
        FROM commerce_order
        WHERE YEAR(created_at) = :year
          AND MONTH(created_at) = :month
        GROUP BY status, currency
        ORDER BY status ASC
    ";

        $stmt = $this->connection->prepare($query);
        $stmt->execute([
            ':year'  => $year,
            ':month' => $month,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];

        foreach ($rows as $row) {
            $status = $row['status'];

            $result[$status] = [
                'orders'         => (int) $row['total_orders'],
                'subtotal'       => (float) $row['total_subtotal'],
                'tax_total'      => (float) $row['total_tax'],
                'discount_total' => (float) $row['total_discount'],
                'shipping_total' => (float) $row['total_shipping'],
                'grand_total'    => (float) $row['total_grand'],
                'currency'        => $row['currency'],
            ];
        }

        return $result;
    }

    public function groupInPairs(array $summary): array
    {
        $result = [];
        $pair = [];
        $counter = 0;

        foreach ($summary as $status => $data) {
            $pair[] = [$status => $data];

            if (count($pair) === 2) {
                $result[$counter++] = $pair;
                $pair = [];
            }
        }

        // if odd count, push last remaining
        if (!empty($pair)) {
            $result[$counter] = $pair;
        }

        return $result;
    }

    public function getSummaryOf(int $year, int $month, string $status): array
    {
        $data = $this->getThisMonthSummary($year, $month);
        return $data[$status] ?? [];
    }

    public function getSummaryYearOf(int $year, string $status)
    {
        $query = "
        SELECT 
            currency,
            status,
            COUNT(*) AS total_orders,
            SUM(subtotal) AS total_subtotal,
            SUM(tax_total) AS total_tax,
            SUM(discount_total) AS total_discount,
            SUM(shipping_total) AS total_shipping,
            SUM(grand_total) AS total_grand
        FROM commerce_order
        WHERE YEAR(created_at) = :year
         AND status = :status
        GROUP BY status, currency
        ORDER BY status ASC
    ";

        $stmt = $this->connection->prepare($query);
        $stmt->execute([
            ':year'  => $year,
            ':status' => $status,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];

        foreach ($rows as $row) {
            $status = $row['status'];

            $result[$status] = [
                'orders'         => (int) $row['total_orders'],
                'subtotal'       => (float) $row['total_subtotal'],
                'tax_total'      => (float) $row['total_tax'],
                'discount_total' => (float) $row['total_discount'],
                'shipping_total' => (float) $row['total_shipping'],
                'grand_total'    => (float) $row['total_grand'],
                'currency'        => $row['currency'],
            ];
        }

        return $result;
    }

    public function getQuickSummaryOfPast(int $startYear, string $status): array
    {
        $currentYear = (int) date('Y');
        $result = [];
        $totalGrandSum = 0;
        $yearlyTotals = [];

        // Loop through each year from $startYear to current year
        for ($year = $startYear; $year <= $currentYear; $year++) {

            $query = "
            SELECT 
                currency,
                status,
                COUNT(*) AS total_orders,
                SUM(subtotal) AS total_subtotal,
                SUM(tax_total) AS total_tax,
                SUM(discount_total) AS total_discount,
                SUM(shipping_total) AS total_shipping,
                SUM(grand_total) AS total_grand
            FROM commerce_order
            WHERE YEAR(created_at) = :year
              AND status = :status
            GROUP BY status, currency
            ORDER BY status ASC
        ";

            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                ':year' => $year,
                ':status' => $status,
            ]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sum grand totals for this year
            $yearGrandTotal = 0;
            foreach ($rows as $row) {
                $yearGrandTotal += (float) $row['total_grand'];
            }

            $yearlyTotals[$year] = $yearGrandTotal;
            $totalGrandSum += $yearGrandTotal;
        }

        // Build result array
        foreach ($yearlyTotals as $year => $grandTotal) {
            $title = $year === $currentYear ? 'This Year' : "Year $year";

            // Percentage of total
            $percent = $totalGrandSum > 0 ? round(($grandTotal / $totalGrandSum) * 100, 1) : 0;

            $result[$year] = [
                'title' => $title,
                'total' => $grandTotal,
                'percent' => $percent . '%',
            ];
        }

        // Add total row
        $averagePercent = count($yearlyTotals) > 0 ? round(array_sum(array_map(fn($v) => (float) rtrim($v['percent'], '%'), $result)) / count($yearlyTotals), 1) : 0;

        $result['total'] = [
            'title' => 'Total',
            'total' => $totalGrandSum,
            'percent' => $averagePercent . '%',
        ];

        return $result;
    }

}