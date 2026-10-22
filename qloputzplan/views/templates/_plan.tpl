{* Gemeinsame Wochenliste fuer Admin und Teilen-Ansicht. *}
<div class="qpp-sheet">
  <h1 class="qpp-title">Anreise- &amp; Vorbereitungsliste <span class="qpp-week">{$week_title|escape:'html':'UTF-8'}</span></h1>

  {assign var=anyItems value=false}
  {foreach from=$days item=day}
    {if $day.items|count}
      {assign var=anyItems value=true}
      <h2 class="qpp-day">{$day.label|escape:'html':'UTF-8'}</h2>
      {foreach from=$day.items item=item}
        <div class="qpp-row">
          <div class="qpp-left">
            <div class="qpp-unit">{$item.unit|escape:'html':'UTF-8'} putzen</div>
            <div class="qpp-sub">{$item.departure_info|escape:'html':'UTF-8'}</div>
            {if $item.has_next}
              <div class="qpp-prep">{$item.prep_title|escape:'html':'UTF-8'}</div>
              <div class="qpp-persons">{$item.persons_text|escape:'html':'UTF-8'}</div>
              <div class="qpp-sub">{$item.services_text|escape:'html':'UTF-8'}</div>
              <ul class="qpp-check">
                {foreach from=$item.checklist item=c}
                  <li><span class="qpp-box">&#9744;</span> {$c|escape:'html':'UTF-8'}</li>
                {/foreach}
              </ul>
            {/if}
          </div>
          <div class="qpp-right">
            <div class="qpp-next">{$item.next_info|escape:'html':'UTF-8'}</div>
            {if $item.gap_hint}<div class="qpp-gap">{$item.gap_hint|escape:'html':'UTF-8'}</div>{/if}

          </div>
        </div>
      {/foreach}
    {/if}
  {/foreach}
  {if !$anyItems}
    <p class="qpp-empty">In dieser Woche gibt es keine Abreisen.</p>
  {/if}
</div>
