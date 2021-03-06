{combine_script id="comments" load="footer" path="admin/themes/default/js/comments.js"}
<h2>{'User comments'|translate} {$TABSHEET_TITLE}</h2>

<div class="commentFilter">
  <a href="{$F_ACTION}&amp;filter=all" class="{if $filter == 'all'}commentFilterSelected{/if}">{'All'|translate}</a> ({$nb_total})
  | <a href="{$F_ACTION}&amp;filter=pending" class="{if $filter == 'pending'}commentFilterSelected{/if}">{'Waiting'|translate}</a> ({$nb_pending})
{if !empty($navbar) }{include file='navigation_bar.tpl'|@get_extent:'navbar'}{/if}
</div>



{if !empty($comments) }
<form method="post" action="{$F_ACTION}" id="pendingComments">

<table>
  {foreach from=$comments item=comment name=comment}
  <tr valign="top" class="{if $smarty.foreach.comment.index is odd}row2{else}row1{/if}">
    <td style="width:50px;" class="checkComment">
      <input type="checkbox" name="comments[]" value="{$comment.ID}">
    </td>
    <td>
  <div class="comment">
    <a class="illustration" href="{$comment.U_PICTURE}"><img src="{$comment.TN_SRC}"></a>
    <p class="commentHeader">{if $comment.IS_PENDING}<span class="pendingFlag">{'Waiting'|translate}</span> - {/if}{if !empty($comment.IP)}{$comment.IP} - {/if}<strong>{$comment.AUTHOR}</strong> - <em>{$comment.DATE}</em></p>
    <blockquote>{$comment.CONTENT}</blockquote>
  </div>
    </td>
  </tr>
  {/foreach}
</table>

  <p class="checkActions">
    {'Select:'|translate}
    <a href="#" id="commentSelectAll">{'All'|translate}</a>,
    <a href="#" id="commentSelectNone">{'None'|translate}</a>,
    <a href="#" id="commentSelectInvert">{'Invert'|translate}</a>
  </p>

  <p class="bottomButtons">
    <input type="submit" name="validate" value="{'Validate'|translate}">
    <input type="submit" name="reject" value="{'Reject'|translate}">
  </p>

</form>
{/if}
