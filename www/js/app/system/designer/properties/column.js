/**
 * Properties panel for Grid object
 */
Ext.define('designer.properties.GridColumn',{
	extend:'designer.properties.Panel',

	autoLoadData:false,
	
	initComponent:function(){
		
		var me = this;

		var summaryEditor = Ext.create('Ext.form.field.ComboBox',{		
			typeAhead: true,
		    triggerAction: 'all',
		    selectOnTab: true,
		    labelWidth:80,
		    forceSelection:true,
		    queryMode:'local',
		    displayField:'title',
		    valueField:'id',
			store: this.renderersStore
		});

		this.sourceConfig = Ext.apply({		
			'summaryType':{
				editor: Ext.create('Ext.form.field.ComboBox',{
					typeAhead: true,
				    triggerAction: 'all',
				    selectOnTab: true,
				    labelWidth:80,
				    forceSelection:true,
				    queryMode:'local',
				    store: [
				        ['count' , 'count'],
				        ['sum', 'sum'],
				        ['min','min'],
				        ['max','max'],
				        ['average','average']
				    ]
				}),
				renderer:function(v){
					if(Ext.isEmpty(v)){
						return '...';
					}else{
						return v;
					}
				}
			},
			'summaryRenderer':{
				editor:summaryEditor,
				renderer:app.comboBoxRenderer(summaryEditor)
			},
			'renderer':{
				editor: Ext.create('Ext.form.field.Text', {
					listeners: {
						focus: {
							fn: me.showRendererWindow,
							scope: me
						}
					}
				}),
				renderer:function(v){return '...';}
			},
			'items':{
				editor:Ext.create('Ext.form.field.Text',{
					listeners:{
						focus:{
							fn:me.showItemsWindow,
							scope:me
						}
					}
				}),
				renderer:function(v){return '...';}
			}
		} , this.sourceConfig );
		
		this.callParent();		
	},
	showRendererWindow:function(){
		Ext.create('designer.grid.column.RendererWindow',{
			title:desLang.renderer,
			objectName : this.objectName,
			columnId: this.extraParams.id,
			controllerUrl:this.controllerUrl
		}).show().toFront();
	},
	showItemsWindow:function()
	{			
		Ext.create('designer.grid.column.ActionsWindow',{
			title:desLang.items,
	    	objectName : this.objectName,
	    	columnId: this.extraParams.id,
	    	controllerUrl:this.controllerUrl
	    }).show().toFront();
	}
});    
