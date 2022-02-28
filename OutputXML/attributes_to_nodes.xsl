<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:template match="root_product">
    <root_product>
      <xsl:apply-templates/>
    </root_product>
  </xsl:template>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="@*">
        <xsl:element name="{name()}"><xsl:value-of select="."/></xsl:element>
    </xsl:template>
</xsl:stylesheet>
