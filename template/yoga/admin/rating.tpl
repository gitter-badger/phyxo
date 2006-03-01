<h2>{lang:Rating} [{NB_ELEMENTS} {lang:elements}]</h2>

<form action="" method="GET" id="update">
<fieldset>
<legend></legend>
<input type="hidden" name="page" value="rating" />
<ul>

<li><label>{lang:elements per page}: <input type="text" name="display" size="2" value="{DISPLAY}"></label></li>

<li>
<label>{lang:Sort by}:
<select name="order_by">
<!-- BEGIN order_by -->
<option value="{order_by.VALUE}" {order_by.SELECTED}>{order_by.CONTENT}</option>
<!-- END order_by -->
</select>
</label></li>

<li>{lang:Filter}:
<label>
 <input type="radio" class="radio" name="display_filter" {DISPLAY_FILTER_ALL_CHECKED} value="all"/>
 {lang:users} & {lang:guest}
</label>
<label>
 <input type="radio" class="radio" name="display_filter" {DISPLAY_FILTER_USER_CHECKED} value="user"/>
 {lang:users}
</label>
<label>
 <input type="radio" class="radio" name="display_filter" {DISPLAY_FILTER_GUEST_CHECKED} value="guest"/>
 {lang:guest}
</label>
</li>

</ul>

</fieldset>
<p class="bottomButtons">
<input type="submit" value="{lang:Submit}" />
</p>
</form>
<div class="navigationBar">{NAVBAR}</div>
<table width="100%">
<tr class="throw">
  <td>{lang:File}</td>
  <td>{lang:Number of rates}</td>
  <td>{lang:Average rate}</td>
  <td>{lang:Controversy}</td>
  <td>{lang:Sum of rates}</td>
  <td>{lang:Rate}</td>
  <td>{lang:Username}</td>
  <td>{lang:Rate date}</td>
  <td></td>
</tr>
<!-- BEGIN image -->
<tr valign="bottom">
  <td rowspan="{image.NB_RATES_PLUS1}"><a href="{image.U_URL}"><img src="{image.U_THUMB}" alt="{image.FILE}" title="{image.FILE}"></a></td>
  <td rowspan="{image.NB_RATES_PLUS1}"><strong>{image.NB_RATES}/{image.NB_RATES_TOTAL}</strong></td>
  <td rowspan="{image.NB_RATES_PLUS1}"><strong>{image.AVG_RATE}</strong></td>
  <td rowspan="{image.NB_RATES_PLUS1}"><strong>{image.STD_RATE}</strong></td>
  <td rowspan="{image.NB_RATES_PLUS1}" style="border-right: 1px solid;" ><strong>{image.SUM_RATE}</strong></td>
</tr>
<!-- BEGIN rate -->
<tr>
    <td>{image.rate.RATE}</td>
    <td><b>{image.rate.USER}</b></td>
    <td><span class="date">{image.rate.DATE}</span></td>
    <td><a href="{image.rate.U_DELETE}"><img src="{themeconf:icon_dir}/delete.png" class="button" style="border:none;vertical-align:middle; margin-left:5px;" alt="[{lang:delete}]"/></a></td>
</tr>
<!-- END rate -->
<!-- END image -->
</table>

<div class="navigationBar">{NAVBAR}</div>
