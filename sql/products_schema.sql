--
-- Database: `import_csv`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `productId` int(8) NOT NULL,
  `name` varchar(100)  NULL,
  `secondName` varchar(100)  NULL,
  `description` varchar(1000)  NULL,
  `picture` varchar(200) NULL,
  `available` BOOLEAN,
  `price` DECIMAL(10,2) NOT NULL, -- just value, currency is kc by default
  `secondPrice` DECIMAL(10,2) NOT NULL, -- just value, currency is kc by default
  `productnumber` varchar(50)  NOT NULL,
  `previewPicture` varchar(200) NULL,
  `gallery0` varchar(200) NULL, -- it seems maximum 3 pictures are used
  `gallery1` varchar(200) NULL,
  `gallery2` varchar(200) NULL,
  `vat` int(8)  NULL, -- by default 21
  `vatlevel` int(8)  NULL, -- by default 1
  `amountInStock` int(8)  NULL,
  `avaibilityId` int(8)  NULL,
  `ean` varchar(50)  NULL,
  `unsaleable` BOOLEAN,
  `categories` varchar(200)  NULL,
  `changedAt` DATETIME  NULL, -- no such field in CSV, only in API response
  `dt` DATETIME  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- je potřeba uchovávat:
-- - name
-- - secondname
-- -description
-- - picture
-- - available
-- - price  (value, currency) = Nase cena
-- - secondprice (value, currency) = Bezna cena
-- - productnumber
-- - previewPicture
-- - gallery
-- - vat = DPH 
-- - vatlevel
-- - amountinstock
-- - avaibilityid
-- - ean
-- - unsaleable (nepoužíváme, ale možná někdy budem)
-- - categories (optat se Elise, jestli je ok vyzradit naše kategorie jiným firmám)
-- - changeat

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  